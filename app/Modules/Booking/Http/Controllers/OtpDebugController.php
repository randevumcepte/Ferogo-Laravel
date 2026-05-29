<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\PhoneVerification;
use App\Modules\Booking\Services\CustomerTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * GEÇİCİ DEBUG endpoint — SMS provider devreye girene kadar.
 * Sadece admin User'lar erişebilir. SMS provider bağlandıktan sonra silinecek.
 *
 * Kullanım:
 *   1) Filament admin'e login ol
 *   2) Yeni sekmede: /admin-debug/otp?phone=0532XXXXXXX
 *   3) Son kodu gör
 */
class OtpDebugController extends Controller
{
    public function show(Request $request, CustomerTrustService $trust): JsonResponse
    {
        $user = Auth::user();
        if (! $user || $user->type !== 'admin') {
            abort(403, 'Sadece admin.');
        }

        $phone = (string) $request->query('phone', '');
        if (! $phone) {
            return response()->json([
                'error' => 'phone parametresi gerekli. Örn: /admin-debug/otp?phone=0532XXXXXXX',
            ], 422);
        }

        $normalized = $trust->normalizePhone($phone);
        $cached = Cache::get('last_otp_dev:' . $normalized);

        $latest = PhoneVerification::where('phone', $normalized)
            ->latest('id')
            ->first();

        return response()->json([
            'phone_raw'        => $phone,
            'phone_normalized' => $normalized,
            'code_from_cache'  => $cached ?: '(cache boş — yeni kod iste)',
            'last_verification' => $latest ? [
                'id'             => $latest->id,
                'created_at'     => $latest->created_at?->toIso8601String(),
                'expires_at'     => $latest->expires_at?->toIso8601String(),
                'is_expired'     => $latest->isExpired(),
                'verified_at'    => $latest->verified_at?->toIso8601String(),
                'attempts'       => $latest->attempts,
                'ip'             => $latest->ip,
            ] : null,
            'hint' => $cached
                ? '✅ Kodu sayfada gir.'
                : 'Telefondan "Tekrar gönder" bas ve bu sayfayı yenile.',
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
