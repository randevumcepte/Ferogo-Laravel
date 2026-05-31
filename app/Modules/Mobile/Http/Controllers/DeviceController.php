<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mobile\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mobil cihaz yönetimi.
 *  - FCM push token kayıt/güncelleme
 *  - Kullanıcının aktif cihazlarını listele
 *  - Belirli bir cihazın oturumunu iptal et (uzaktan logout)
 *
 * Tüm endpoint'ler auth:sanctum + device middleware ardında.
 */
class DeviceController extends Controller
{
    /**
     * POST /api/v1/devices/push-token
     * Body: { fcm_token }
     *
     * Login sonrası ilk açılışta veya FCM token rotate olduğunda çağrılır.
     */
    public function registerPushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'min:32', 'max:512'],
        ]);

        $user     = $request->user();
        $deviceId = (string) $request->header('X-Device-Id', '');
        $token    = $user->currentAccessToken();

        $row = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if (! $row) {
            return response()->json([
                'ok'      => false,
                'message' => 'Cihaz kaydı bulunamadı.',
                'code'    => 'device_unknown',
            ], 404);
        }

        // Bu FCM token'ı başka bir kullanıcının cihazında geziyorsa temizle
        // (cihaz farklı kullanıcı hesabıyla yeniden login olmuş olabilir)
        DeviceToken::where('fcm_token', $validated['fcm_token'])
            ->where('id', '!=', $row->id)
            ->update(['fcm_token' => null]);

        $row->forceFill([
            'fcm_token'                => $validated['fcm_token'],
            'personal_access_token_id' => $token instanceof PersonalAccessToken ? $token->id : $row->personal_access_token_id,
            'last_seen_at'             => now(),
            'last_ip'                  => $request->ip(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/v1/devices
     * Kullanıcının bağlı cihazlarını döner — "Diğer cihazlardan çıkış" UI'sı için.
     */
    public function index(Request $request): JsonResponse
    {
        $user        = $request->user();
        $currentBind = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('device_id', (string) $request->header('X-Device-Id', ''))
            ->first();

        $rows = DeviceToken::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->get();

        return response()->json([
            'ok'       => true,
            'current'  => $currentBind?->id,
            'devices'  => $rows->map(fn (DeviceToken $d) => [
                'id'           => $d->id,
                'platform'     => $d->platform,
                'device_model' => $d->device_model,
                'app_version'  => $d->app_version,
                'os_version'   => $d->os_version,
                'last_ip'      => $d->last_ip,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                'is_current'   => $currentBind && $currentBind->id === $d->id,
            ])->values(),
        ]);
    }

    /**
     * DELETE /api/v1/devices/{id}
     * Belirli bir cihazın token'ını iptal eder (mevcut cihaz dahil).
     * Token theft şüphesinde kritik.
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $row  = DeviceToken::where('user_id', $user->id)->find($id);

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Cihaz bulunamadı.'], 404);
        }

        if ($row->personal_access_token_id) {
            $user->tokens()->where('id', $row->personal_access_token_id)->delete();
        }
        $row->delete();

        return response()->json(['ok' => true]);
    }
}
