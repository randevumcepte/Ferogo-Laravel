<?php

namespace App\Http\Middleware;

use App\Modules\Mobile\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticated mobile request'lerde:
 *  1) İstemcinin X-Device-Id header'ını, Sanctum tokenine bağlı device_token kaydı ile karşılaştırır
 *  2) Eşleşmezse 401 + token_revoked döner (token theft savunması)
 *  3) Eşleşirse last_seen_at + last_ip'i günceller (audit / "diğer cihazlarda aktif" UI'sı için)
 *
 * Tokeni başka cihaza taşıyan saldırgan X-Device-Id'i bilse bile, mobil tarafta secure_storage'da
 * tutulan device_id ile token rotasyonu ek katman sağlar.
 */
class TouchDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            // TransientToken (session) — mobil değil, geç
            return $next($request);
        }

        $deviceId = (string) $request->header('X-Device-Id', '');
        if ($deviceId === '' || strlen($deviceId) > 64) {
            return response()->json([
                'ok'      => false,
                'message' => 'Cihaz kimliği eksik.',
                'code'    => 'device_id_required',
            ], 400);
        }

        $binding = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('personal_access_token_id', $token->id)
            ->first();

        // Token bağlı olduğu cihazdan başka bir device_id ile geliyorsa → token sızdırılmış demek
        if ($binding && $binding->device_id !== $deviceId) {
            // Tüm güvenlik katmanları: bu token'ı anında iptal et
            $token->delete();
            DeviceToken::where('personal_access_token_id', $token->id)->delete();

            return response()->json([
                'ok'      => false,
                'message' => 'Oturumun güvenlik nedeniyle sonlandırıldı. Tekrar giriş yap.',
                'code'    => 'token_revoked',
            ], 401);
        }

        // İlk istek için bağlama henüz yoksa (login'den sonra device kayıt yapmamış) → izin ver
        // ama log et: kullanıcı /devices endpoint'ini hemen çağırmalı
        if ($binding) {
            $binding->forceFill([
                'last_seen_at' => now(),
                'last_ip'      => $request->ip(),
            ])->saveQuietly();
        }

        return $next($request);
    }
}
