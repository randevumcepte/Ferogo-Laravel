<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use App\Modules\Security\Models\PanicAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Faz 7 — Acil yardım (panic) butonu.
 *
 * Hem müşteri hem sürücü kırmızı "ACİL YARDIM" butonuna basabilir.
 * Sunucuya GPS + ride_id + kim olduğu gönderilir → Filament operatör
 * panelinde KRİTİK ALARM olarak görünür.
 */
class PanicAlertController extends Controller
{
    /**
     * POST /api/panic
     * Body:
     *   triggered_by_type: 'customer' | 'driver'
     *   ride_request_public_id: string|null
     *   lat, lng, location_accuracy_m
     */
    public function trigger(Request $request): JsonResponse
    {
        // Rate-limit: aynı IP'den dakikada max 5 (DoS koruması)
        $rateKey = 'panic:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Çok fazla istek. Lütfen 1 dakika bekleyin.',
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $validated = $request->validate([
            'triggered_by_type'      => ['required', Rule::in([
                PanicAlert::TRIGGER_DRIVER,
                PanicAlert::TRIGGER_CUSTOMER,
            ])],
            'ride_request_public_id' => ['nullable', 'string', 'max:64'],
            'lat'                    => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                    => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy_m'    => ['nullable', 'numeric'],
        ]);

        $rideRequest = null;
        if (! empty($validated['ride_request_public_id'])) {
            $rideRequest = RideRequest::where('public_id', $validated['ride_request_public_id'])->first();
        }

        // Hangi taraftan geldiyse o guard'tan user'ı çek (paralel oturumlar destekli)
        $user = null;
        $driverId = null;
        $phone = null;

        if ($validated['triggered_by_type'] === PanicAlert::TRIGGER_DRIVER) {
            $driverUser = Auth::guard('driver')->user();
            $user = $driverUser;
            if ($driverUser) {
                $driver = Driver::where('user_id', $driverUser->id)->first();
                $driverId = $driver?->id;
                $phone = $driverUser->phone;
            }
        } else {
            $customerUser = Auth::guard('customer')->user();
            $user = $customerUser;
            $phone = $customerUser?->phone ?? $rideRequest?->customer_phone;
        }

        $alert = PanicAlert::create([
            'ride_request_id'       => $rideRequest?->id,
            'ride_id'               => $rideRequest?->ride_id,
            'triggered_by_type'     => $validated['triggered_by_type'],
            'triggered_by_user_id'  => $user?->id,
            'driver_id'             => $driverId,
            'triggered_by_phone'    => $phone,
            'lat'                   => $validated['lat'] ?? null,
            'lng'                   => $validated['lng'] ?? null,
            'location_accuracy_m'   => $validated['location_accuracy_m'] ?? null,
            'ip_address'            => $request->ip(),
            'user_agent'            => mb_substr((string) $request->userAgent(), 0, 1000),
            'device_fingerprint'    => $request->input('fingerprint'),
        ]);

        return response()->json([
            'success'   => true,
            'alert_id'  => $alert->public_id,
            'message'   => 'Acil yardım talebiniz alındı. Çağrı merkezi sizinle anında iletişime geçecek.',
            'call'      => '+908503403039',
        ]);
    }
}
