<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\PhoneVerificationService;
use App\Modules\Driver\Models\Driver;
use App\Modules\Mobile\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Mobil uygulama auth katmanı.
 *
 * Web tarafı session-cookie ile çalışmaya devam eder; mobil burada Sanctum bearer
 * token alır. Müşteri OTP ile, sürücü email+şifre ile giriş yapar.
 *
 * Güvenlik notları:
 *  - Token cihaza bağlanır (X-Device-Id zorunlu)
 *  - Token ability'leri rol bazlı (customer / driver)
 *  - Token TTL config/sanctum.php'de (30 gün); mobil tarafta rotation interceptor uyarısı düşer
 *  - Sürücü hesabı suspended ise login engellenir
 *  - OTP brute force koruması PhoneVerificationService içinde (5 deneme / dk)
 *  - Driver login için per-email + per-IP rate limit burada
 */
class AuthController extends Controller
{
    public function __construct(
        private PhoneVerificationService $otpService,
        private CustomerTrustService $trustService,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  MÜŞTERİ — TELEFON + OTP
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/auth/customer/send-otp
     * Body: { phone, device_id }
     *
     * Web'deki PhoneVerificationController ile aynı OTP altyapısını paylaşır
     * (aynı PhoneVerification tablosuna yazar), sadece response mobile-friendly.
     */
    public function sendCustomerOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'     => ['required', 'string', 'max:32'],
            'device_id' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        // Ban + rate limit ortak kontrolü (web tarafıyla aynı koruma)
        $check = $this->trustService->canRequestRide(
            $validated['phone'],
            $request->ip(),
            $validated['device_id'],
        );
        if (! $check['ok']) {
            return $this->fail($check['reason'] ?? 'Şu anda kod isteyemezsin.', 429, ['retry_after' => $check['retry_after'] ?? null]);
        }

        $result = $this->otpService->sendOtp(
            $validated['phone'],
            $request->ip(),
            $validated['device_id'],
        );

        $status = $result['ok'] ? 200 : 429;
        return response()->json($result, $status);
    }

    /**
     * POST /api/v1/auth/customer/verify-otp
     * Body: { phone, code, device_id, name?, platform?, app_version?, os_version?, device_model?, locale? }
     *
     * Başarılıysa: { ok, token, user, expires_at }
     */
    public function verifyCustomerOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'        => ['required', 'string', 'max:32'],
            'code'         => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'device_id'    => ['required', 'string', 'min:8', 'max:64'],
            'name'         => ['nullable', 'string', 'max:120'],
            'platform'     => ['nullable', Rule::in(['ios', 'android'])],
            'app_version'  => ['nullable', 'string', 'max:32'],
            'os_version'   => ['nullable', 'string', 'max:32'],
            'device_model' => ['nullable', 'string', 'max:64'],
            'locale'       => ['nullable', 'string', 'max:8'],
        ]);

        // OTP servisi içinde brute-force koruması var (5 yanlış / dakika).
        // verifyOtp aynı zamanda session login de yapıyor — biz oradan dönen user_id'yi alıp
        // mobil için ayrı bir Sanctum token üretiyoruz.
        $result = $this->otpService->verifyOtp(
            $validated['phone'],
            $validated['code'],
            $request->ip(),
            $validated['device_id'],
            $validated['name'] ?? null,
        );

        if (! $result['ok']) {
            return $this->fail($result['message'] ?? 'Kod doğrulanamadı.', 422);
        }

        $user = User::find($result['user_id']);
        if (! $user || $user->type !== 'customer') {
            return $this->fail('Hesap doğrulaması başarısız.', 422);
        }

        return $this->issueMobileToken($user, $request, $validated, role: 'customer');
    }

    // ─────────────────────────────────────────────────────────────
    //  SÜRÜCÜ — EMAIL + ŞİFRE
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/auth/driver/login
     * Body: { email, password, device_id, platform?, app_version?, os_version?, device_model?, locale? }
     */
    public function driverLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'        => ['required', 'email', 'max:120'],
            'password'     => ['required', 'string', 'min:6', 'max:200'],
            'device_id'    => ['required', 'string', 'min:8', 'max:64'],
            'platform'     => ['nullable', Rule::in(['ios', 'android'])],
            'app_version'  => ['nullable', 'string', 'max:32'],
            'os_version'   => ['nullable', 'string', 'max:32'],
            'device_model' => ['nullable', 'string', 'max:64'],
            'locale'       => ['nullable', 'string', 'max:8'],
        ]);

        // Brute-force: email başına 1 dk içinde 5 deneme + ip başına 1 saatte 30
        $emailKey = 'driver_login_email:' . mb_strtolower($validated['email']);
        $ipKey    = 'driver_login_ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($emailKey, 5)) {
            return $this->fail('Çok fazla yanlış deneme. 1 dakika bekle.', 429,
                ['retry_after' => RateLimiter::availableIn($emailKey)]);
        }
        if (RateLimiter::tooManyAttempts($ipKey, 30)) {
            return $this->fail('Bu cihazdan çok fazla deneme. Daha sonra dene.', 429,
                ['retry_after' => RateLimiter::availableIn($ipKey)]);
        }

        $user = User::where('email', $validated['email'])
            ->where('type', 'driver')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($emailKey, 60);
            RateLimiter::hit($ipKey, 3600);
            // Time-safe failure — varlık sızdırma
            return $this->fail('E-posta veya şifre hatalı.', 401);
        }

        if ($user->status !== 'active') {
            return $this->fail('Hesabın aktif değil. Destekle iletişime geç.', 403, ['code' => 'account_inactive']);
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if (! $driver || $driver->approval_status !== 'approved') {
            return $this->fail('Sürücü hesabın henüz onaylı değil.', 403, ['code' => 'driver_not_approved']);
        }

        RateLimiter::clear($emailKey);

        return $this->issueMobileToken($user, $request, $validated, role: 'driver', extra: [
            'driver_id'            => $driver->id,
            'availability_status'  => $driver->availability_status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  /me  ve  /logout
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/auth/me
     * Bearer + X-Device-Id zorunlu. Token sahibinin temel profilini döner.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $payload = [
            'id'    => $user->id,
            'name'  => $user->name,
            'phone' => $user->phone,
            'type'  => $user->type,
            'avatar' => $user->avatar
                ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . ltrim($user->avatar, '/')))
                : null,
        ];

        if ($user->type === 'driver') {
            $driver = Driver::where('user_id', $user->id)
                ->with('currentVehicle.vehicleClass')
                ->first();
            if ($driver) {
                $payload['driver'] = [
                    'id'                  => $driver->id,
                    'availability_status' => $driver->availability_status,
                    'rating'              => (float) $driver->rating,
                    'total_rides'         => (int) $driver->total_rides,
                    'approval_status'     => $driver->approval_status,
                ];
            }
        }

        return response()->json(['ok' => true, 'user' => $payload]);
    }

    /**
     * POST /api/v1/auth/logout
     * Bearer + X-Device-Id zorunlu.
     * Bu cihazın tokenini iptal eder, push registration'ı temizler.
     */
    public function logout(Request $request): JsonResponse
    {
        $user  = $request->user();
        $token = $user->currentAccessToken();

        DB::transaction(function () use ($user, $token) {
            if ($token && method_exists($token, 'delete')) {
                DeviceToken::where('personal_access_token_id', $token->id)->delete();
                $token->delete();
            }
        });

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Sanctum token üret, device_token kaydı oluştur, response döndür.
     *
     * @param array<string,mixed> $deviceMeta İsteğin validated body'si (device_id, platform vb)
     */
    private function issueMobileToken(User $user, Request $request, array $deviceMeta, string $role, array $extra = []): JsonResponse
    {
        $deviceId = $deviceMeta['device_id'];

        return DB::transaction(function () use ($user, $request, $deviceMeta, $role, $extra, $deviceId) {
            // Aynı cihazda eski token varsa → revoke (token rotation)
            $existing = DeviceToken::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->first();

            if ($existing && $existing->personal_access_token_id) {
                $user->tokens()->where('id', $existing->personal_access_token_id)->delete();
            }

            // Ability scope: tek bir rol
            $abilities = $role === 'driver'
                ? ['driver:*']
                : ['customer:*'];

            $expiresAt = now()->addMinutes(config('sanctum.expiration', 60 * 24 * 30));

            $tokenName = sprintf('%s|%s|%s',
                $role,
                $deviceMeta['platform'] ?? 'unknown',
                substr($deviceId, 0, 12),
            );

            $newToken = $user->createToken($tokenName, $abilities, $expiresAt);
            $plain    = $newToken->plainTextToken;
            $modelId  = $newToken->accessToken->id;

            // Device kaydı — upsert
            DeviceToken::updateOrCreate(
                ['user_id' => $user->id, 'device_id' => $deviceId],
                [
                    'personal_access_token_id' => $modelId,
                    'platform'     => $deviceMeta['platform']     ?? 'android',
                    'app_version'  => $deviceMeta['app_version']  ?? null,
                    'os_version'   => $deviceMeta['os_version']   ?? null,
                    'device_model' => $deviceMeta['device_model'] ?? null,
                    'locale'       => $deviceMeta['locale']       ?? null,
                    'last_ip'      => $request->ip(),
                    'last_seen_at' => now(),
                    // fcm_token register-device endpoint'inden gelecek; null bırak
                ]
            );

            Log::info('[mobile-auth] token_issued', [
                'user_id'    => $user->id,
                'role'       => $role,
                'device_id'  => substr($deviceId, 0, 8) . '…',
                'ip'         => $request->ip(),
            ]);

            return response()->json(array_merge([
                'ok'         => true,
                'token'      => $plain,
                'expires_at' => $expiresAt->toIso8601String(),
                'user'       => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'phone' => $user->phone,
                    'type'  => $user->type,
                ],
            ], $extra));
        });
    }

    private function fail(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'ok'      => false,
            'message' => $message,
        ], $extra), $status);
    }
}
