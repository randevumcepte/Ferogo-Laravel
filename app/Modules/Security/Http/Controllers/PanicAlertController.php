<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use App\Modules\Security\Models\PanicAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        // Operatöre SMS — yanıtı bekletmeden, sürücüyü yavaşlatmadan gönder.
        dispatch(function () use ($alert) {
            $this->notifyOperators($alert);
        })->afterResponse();

        return response()->json([
            'success'   => true,
            'alert_id'  => $alert->public_id,
            'message'   => 'Acil yardım talebiniz alındı. Çağrı merkezi sizinle anında iletişime geçecek.',
            'call'      => '+908503403039',
        ]);
    }

    /**
     * Nöbetçi operatör(ler)e panik alarmını SMS ile haber ver.
     * services.panic.operator_phones boşsa sessizce atlar (sadece log).
     */
    protected function notifyOperators(PanicAlert $alert): void
    {
        if (! config('services.panic.sms_enabled', true)) {
            return;
        }

        $phones = config('services.panic.operator_phones', []);
        if (empty($phones)) {
            Log::warning('[Panic] Operatör SMS atlandı — PANIC_OPERATOR_PHONES tanımlı değil.', [
                'alert_id' => $alert->public_id,
            ]);
            return;
        }

        $who    = $alert->triggered_by_type === PanicAlert::TRIGGER_DRIVER ? 'SÜRÜCÜ' : 'YOLCU';
        $phone  = $alert->triggered_by_phone ?: '-';
        $mapUrl = ($alert->lat && $alert->lng)
            ? sprintf('maps.google.com/?q=%s,%s', $alert->lat, $alert->lng)
            : 'konum yok';

        $content = sprintf(
            'FERXGO ACIL YARDIM! %s panik butonuna basti. Tel: %s. Konum: %s. Hemen panele bakin ve arayin.',
            $who,
            $phone,
            $mapUrl
        );

        $sms = app(VoiceTelekomClient::class);
        foreach ($phones as $to) {
            try {
                $res = $sms->sendSingle($to, $content);
                if (! ($res['ok'] ?? false)) {
                    Log::error('[Panic] Operatör SMS gönderilemedi', [
                        'alert_id' => $alert->public_id,
                        'message'  => $res['message'] ?? 'unknown',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('[Panic] Operatör SMS istisna: ' . $e->getMessage(), [
                    'alert_id' => $alert->public_id,
                ]);
            }
        }
    }

    /**
     * GET /admin/panic-poll
     * Admin panelinin JS dinleyicisi buradan açık alarmları çeker → sesli/görsel pop-up.
     * Sadece son 30 dakikanın açık (çözülmemiş) alarmları döner.
     */
    public function poll(): JsonResponse
    {
        $open = [
            PanicAlert::STATUS_TRIGGERED,
            PanicAlert::STATUS_ACKNOWLEDGED,
            PanicAlert::STATUS_CONTACTING,
            PanicAlert::STATUS_POLICE_DISPATCHED,
        ];

        $alerts = PanicAlert::query()
            ->with('ride.customer', 'driver.user', 'triggeredByUser')
            ->whereIn('status', $open)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (PanicAlert $a) {
                // Panik ride olmadan da tetiklenebilir → ismi doğrudan ilişkilerden çek
                $name = $a->triggered_by_type === PanicAlert::TRIGGER_DRIVER
                    ? ($a->driver?->user?->name ?? $a->triggeredByUser?->name)
                    : ($a->triggeredByUser?->name ?? $a->ride?->customer?->name);

                return [
                    'id'       => $a->id,
                    'who'      => $a->triggered_by_type === PanicAlert::TRIGGER_DRIVER ? 'Sürücü' : 'Yolcu',
                    'phone'    => $a->triggered_by_phone,
                    'name'     => $name,
                    'lat'      => $a->lat,
                    'lng'      => $a->lng,
                    'map_url'  => ($a->lat && $a->lng)
                        ? 'https://www.google.com/maps?q=' . $a->lat . ',' . $a->lng
                        : null,
                    'ago'      => $a->created_at?->diffForHumans(),
                    'url'      => url('/admin/panic-alerts/' . $a->id),
                ];
            });

        return response()->json([
            'count'  => $alerts->count(),
            'alerts' => $alerts,
        ]);
    }
}
