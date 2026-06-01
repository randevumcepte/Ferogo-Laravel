<?php

namespace App\Modules\Booking\Services;

use App\Models\User;
use App\Modules\Booking\Models\PhoneVerification;
use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        private VoiceTelekomClient $smsClient,
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
     * Doğrulama başarılı olursa müşteri User kaydı otomatik yaratılır/bulunur
     * ve session'a login edilir. Yani OTP = kayıt + login adımı.
     *
     * @return array{ok: bool, message?: string, token?: string, user_id?: int}
     */
    public function verifyOtp(
        string $phone,
        string $code,
        ?string $ip = null,
        ?string $fingerprint = null,
        ?string $name = null,
    ): array {
        $normalized = $this->trustService->normalizePhone($phone);

        // Brute-force koruması: telefon başına 1 dk içinde max 5 deneme
        $rlKey = self::RL_VERIFY . $normalized;
        if (RateLimiter::tooManyAttempts($rlKey, self::MAX_ATTEMPTS)) {
            return [
                'ok'      => false,
                'message' => 'Çok fazla yanlış deneme. 1 dakika bekle.',
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

        // Doğrulandı → token üret + müşteri hesabı oluştur/güncelle + login
        return DB::transaction(function () use ($verification, $normalized, $ip, $fingerprint, $name) {
            $token = bin2hex(random_bytes(24));
            $verification->update([
                'verified_at'        => now(),
                'verification_token' => $token,
                'token_expires_at'   => now()->addSeconds(self::TOKEN_TTL_SECONDS),
                'ip'                 => $ip ?? $verification->ip,
                'fingerprint'        => $fingerprint ?? $verification->fingerprint,
            ]);

            PhoneVerification::where('phone', $normalized)
                ->where('id', '!=', $verification->id)
                ->whereNull('verified_at')
                ->delete();

            $user = $this->findOrCreateCustomer($normalized, $name);

            // Session auth — MÜŞTERİ guard (sürücü oturumundan tamamen bağımsız).
            // Aynı tarayıcıda hem müşteri hem sürücü olarak login kalınabilir.
            Auth::guard('customer')->login($user, remember: true);

            return [
                'ok'      => true,
                'token'   => $token,
                'user_id' => $user->id,
            ];
        });
    }

    /**
     * Telefon ile müşteri kaydı bul/oluştur. Synthetic email kullanılır
     * çünkü müşteri girişi sadece OTP ile — email ile login yok.
     */
    protected function findOrCreateCustomer(string $normalizedPhone, ?string $name = null): User
    {
        // 1) Phone match — type fark etmeksizin (driver ise dokunma, döndür)
        $existing = User::where('phone', $normalizedPhone)->first();

        if ($existing) {
            $updates = ['phone_verified_at' => now()];
            if ($name && $existing->type === 'customer' && (!$existing->name || $existing->name === 'Müşteri')) {
                $updates['name'] = mb_substr($name, 0, 120);
            }
            $existing->update($updates);
            return $existing;
        }

        // 2) Yoksa yeni müşteri
        $syntheticEmail = 'c' . $normalizedPhone . '@ferogo.local';

        // Aynı synthetic email zaten varsa (eski kayıt, farklı normalizasyon vs.) — yeniden kullan
        $byEmail = User::where('email', $syntheticEmail)->first();
        if ($byEmail) {
            $byEmail->update([
                'phone'             => $normalizedPhone,
                'phone_verified_at' => now(),
            ]);
            return $byEmail;
        }

        return User::create([
            'name'              => $name ? mb_substr($name, 0, 120) : 'Müşteri',
            'email'             => $syntheticEmail,
            'password'          => Hash::make(Str::random(40)), // OTP login, şifre kullanılmaz
            'type'              => 'customer',
            'phone'             => $normalizedPhone,
            'phone_verified_at' => now(),
            'status'            => 'active',
        ]);
    }

    /**
     * Ride request store ederken çağrılır. Token geçerliyse telefon eşleşmesini kontrol eder
     * ve token'ı tüketmeden döndürür (token 24 saat geçerli, tek kullanım değil).
     *
     * Yan etki: oturum yoksa kullanıcıyı otomatik login eder (token = telefonu kanıtlar,
     * doğrulanmış kullanıcının panel erişimi olması doğal).
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

        // Session yoksa veya farklı kullanıcı login ise → bu telefonun User'ını login et
        // (MÜŞTERİ guard — sürücü oturumu paralel kalır)
        if (! Auth::guard('customer')->check() || Auth::guard('customer')->user()?->phone !== $normalized) {
            $user = User::where('phone', $normalized)->first();
            if ($user && $user->status === 'active') {
                Auth::guard('customer')->login($user, remember: true);
            }
        }

        return ['ok' => true];
    }

    /**
     * SMS gönderme — Voice Telekom provider'a bağlı.
     * Log + cache yedek olarak kalıyor: provider çökerse otp:last komutu + admin debug
     * endpoint'i sayesinde hâlâ test edilebilir.
     */
    protected function sendSms(string $phone, string $code): void
    {
        $message = "Ferogo dogrulama kodun: {$code}. Kimseyle paylasma. Kod 5 dakika gecerli.";

        // Yedek: log + cache (10 dk)
        Log::info('[OTP] Telefon: ' . $phone . ' Kod: ' . $code);
        Cache::put('last_otp_dev:' . $phone, $code, now()->addMinutes(10));

        // Gerçek SMS — Voice Telekom (.env'de VOICETELEKOM_ENABLED=true ise gönderilir)
        // Not: VT'ye giden bir "OTP protocol" SMS değil, içeriği doğrulama kodu olan
        // normal bilgilendirme SMS'i (sms/create endpoint, sendSingleSms semantiği).
        $result = $this->smsClient->sendSingle($phone, $message);

        if (! $result['ok']) {
            Log::warning('[OTP] SMS gönderilemedi (yedek log/cache aktif): ' . ($result['message'] ?? '?'));
        }
    }
}
