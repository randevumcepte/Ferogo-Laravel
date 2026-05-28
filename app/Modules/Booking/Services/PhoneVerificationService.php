<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\PhoneVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * SMS OTP altyapısı.
 *
 * Şimdilik SMS provider entegrasyonu yok — kodu log'a ve cache'e yazıyor (development).
 * Production'da `sendSms()` Netgsm/İletimerkezi/Twilio'ya bağlanır.
 *
 * Akış:
 *   1. sendOtp(phone) → 6 haneli kod üret, hash'le DB'ye yaz, SMS gönder
 *   2. verifyOtp(phone, code) → kod doğruysa verification_token döner
 *   3. ride_request store ederken bu token ile gelir → consumeToken() yakalar
 *
 * Token TTL: 24 saat. Yani bir kez doğrulanan telefon 24 saat boyunca tekrar OTP istemeden çağrı yapabilir.
 */
class PhoneVerificationService
{
    public const CODE_TTL_SECONDS  = 300;   // 5 dk
    public const TOKEN_TTL_SECONDS = 86400; // 24 saat
    public const MAX_ATTEMPTS      = 5;

    // Rate limit anahtarları
    private const RL_SEND_PHONE = 'otp_send_phone:';
    private const RL_SEND_IP    = 'otp_send_ip:';
    private const RL_VERIFY     = 'otp_verify:';

    public function __construct(
        private CustomerTrustService $trustService,
    ) {}

    /**
     * Bu telefonun zaten geçerli bir doğrulama jetonu var mı? (24 saat içinde tekrar OTP istemesin)
     */
    public function activeToken(string $phone): ?string
    {
        $normalized = $this->trustService->normalizePhone($phone);

        $v = PhoneVerification::where('phone', $normalized)
            ->whereNotNull('verification_token')
            ->whereNull('token_used_at')
            ->where('token_expires_at', '>', now())
            ->latest('id')
            ->first();

        return $v?->verification_token;
    }

    /**
     * OTP gönder. Rate limit + spam koruması burada.
     *
     * @return array{ok: bool, message?: string, retry_after?: int, dev_code?: string}
     */
    public function sendOtp(string $phone, ?string $ip = null, ?string $fingerprint = null): array
    {
        $normalized = $this->trustService->normalizePhone($phone);

        if (strlen($normalized) < 10) {
            return ['ok' => false, 'message' => 'Geçersiz telefon numarası.'];
        }

        // Telefon başına: 1 dakikada 1, 1 saatte 5
        $perMinuteKey = self::RL_SEND_PHONE . 'm:' . $normalized;
        if (RateLimiter::tooManyAttempts($perMinuteKey, 1)) {
            return [
                'ok'          => false,
                'message'     => 'Çok sık kod isteği. 1 dakika bekle.',
                'retry_after' => RateLimiter::availableIn($perMinuteKey),
            ];
        }
        $perHourKey = self::RL_SEND_PHONE . 'h:' . $normalized;
        if (RateLimiter::tooManyAttempts($perHourKey, 5)) {
            return [
                'ok'          => false,
                'message'     => 'Bu telefon için saatlik kod limiti doldu.',
                'retry_after' => RateLimiter::availableIn($perHourKey),
            ];
        }

        // IP başına: 1 saatte 10 (farklı telefonlardan gelse de)
        if ($ip) {
            $ipKey = self::RL_SEND_IP . $ip;
            if (RateLimiter::tooManyAttempts($ipKey, 10)) {
                return [
                    'ok'          => false,
                    'message'     => 'Çok fazla istek. Daha sonra dene.',
                    'retry_after' => RateLimiter::availableIn($ipKey),
                ];
            }
            RateLimiter::hit($ipKey, 3600);
        }

        RateLimiter::hit($perMinuteKey, 60);
        RateLimiter::hit($perHourKey, 3600);

        // 6 haneli kod
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneVerification::create([
            'phone'       => $normalized,
            'code_hash'   => Hash::make($code),
            'attempts'    => 0,
            'expires_at'  => now()->addSeconds(self::CODE_TTL_SECONDS),
            'ip'          => $ip,
            'fingerprint' => $fingerprint,
        ]);

        $this->sendSms($normalized, $code);

        $response = ['ok' => true, 'message' => 'Kod telefonuna gönderildi.'];

        // Development modunda kodu cevapta da gönder (UI test kolaylığı)
        if (config('app.debug')) {
            $response['dev_code'] = $code;
        }

        return $response;
    }

    /**
     * Kod doğrula → ride_request'te kullanılacak token döner.
     *
     * @return array{ok: bool, message?: string, token?: string}
     */
    public function verifyOtp(string $phone, string $code, ?string $ip = null, ?string $fingerprint = null): array
    {
        $normalized = $this->trustService->normalizePhone($phone);

        // Brute-force koruması: telefon başına 1 dk içinde max 5 deneme
        $rlKey = self::RL_VERIFY . $normalized;
        if (RateLimiter::tooManyAttempts($rlKey, self::MAX_ATTEMPTS)) {
            return [
                'ok'          => false,
                'message'     => 'Çok fazla yanlış deneme. 1 dakika bekle.',
            ];
        }
        RateLimiter::hit($rlKey, 60);

        $verification = PhoneVerification::where('phone', $normalized)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $verification) {
            return ['ok' => false, 'message' => 'Kodun süresi dolmuş. Yeni kod iste.'];
        }

        $verification->increment('attempts');

        if ($verification->attempts > self::MAX_ATTEMPTS) {
            return ['ok' => false, 'message' => 'Çok fazla yanlış deneme. Yeni kod iste.'];
        }

        if (! Hash::check($code, $verification->code_hash)) {
            return ['ok' => false, 'message' => 'Kod hatalı. Tekrar dene.'];
        }

        // Doğrulandı → token üret
        $token = bin2hex(random_bytes(24));
        $verification->update([
            'verified_at'        => now(),
            'verification_token' => $token,
            'token_expires_at'   => now()->addSeconds(self::TOKEN_TTL_SECONDS),
            'ip'                 => $ip ?? $verification->ip,
            'fingerprint'        => $fingerprint ?? $verification->fingerprint,
        ]);

        // Bu telefondaki diğer pending kodları çöpe at
        PhoneVerification::where('phone', $normalized)
            ->where('id', '!=', $verification->id)
            ->whereNull('verified_at')
            ->delete();

        return ['ok' => true, 'token' => $token];
    }

    /**
     * Ride request store ederken çağrılır. Token geçerliyse telefon eşleşmesini kontrol eder
     * ve token'ı tüketmeden döndürür (token 24 saat geçerli, tek kullanım değil).
     *
     * @return array{ok: bool, message?: string}
     */
    public function validateToken(string $phone, ?string $token): array
    {
        if (! $token) {
            return ['ok' => false, 'message' => 'Telefon doğrulama bilgisi eksik. SMS ile gelen kodu gir.'];
        }

        $normalized = $this->trustService->normalizePhone($phone);

        $v = PhoneVerification::where('verification_token', $token)
            ->where('phone', $normalized)
            ->whereNotNull('verified_at')
            ->where('token_expires_at', '>', now())
            ->first();

        if (! $v) {
            return ['ok' => false, 'message' => 'Telefon doğrulaması geçersiz veya süresi dolmuş. Tekrar doğrula.'];
        }

        return ['ok' => true];
    }

    /**
     * SMS gönderme stub'ı. Production'da gerçek provider'a bağlanır.
     */
    protected function sendSms(string $phone, string $code): void
    {
        $message = "Ferogo doğrulama kodun: {$code}. Kimseyle paylaşma.";

        Log::info('[OTP] Telefon: ' . $phone . ' Kod: ' . $code . ' — ' . $message);

        Cache::put('last_otp_dev:' . $phone, $code, now()->addMinutes(10));

        // TODO production: Netgsm / İletimerkezi / Twilio entegrasyonu
        // app(SmsProvider::class)->send($phone, $message);
    }
}
