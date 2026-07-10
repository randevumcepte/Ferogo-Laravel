<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use App\Modules\Security\Models\PanicAlert;
use App\Modules\Security\Models\PanicCallSignal;
use App\Modules\Security\Services\ClickToCallService;
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

        // Operatöre SMS + otomatik click-to-call — yanıtı bekletmeden (afterResponse).
        dispatch(function () use ($alert) {
            $this->notifyOperators($alert);
            $this->autoCall($alert);
        })->afterResponse();

        return response()->json([
            'success'   => true,
            'alert_id'  => $alert->public_id,
            'message'   => 'Acil yardım talebiniz alındı. Çağrı merkezi sizinle anında iletişime geçecek.',
            'call'      => '+908503403039',
        ]);
    }

    /**
     * POST /api/panic/{publicId}/location
     * Panik gönderildikten sonra cihaz CANLI konumu gönderir (watchPosition).
     * public_id (ULID) ile yetkilendirilir; kapalı/çözülmüş alarmın konumu güncellenmez.
     * Daha DÜŞÜK doğruluklu (accuracy sayısı büyük) yeni fix, mevcut daha iyi fix'i ezmez.
     */
    public function updateLocation(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'lat'                 => ['required', 'numeric', 'between:-90,90'],
            'lng'                 => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $alert = PanicAlert::where('public_id', $publicId)->firstOrFail();

        // Çözülmüş/yanlış alarm ise dokunma (geç gelen konum kapanmış vakayı bozmasın).
        if (in_array($alert->status, [PanicAlert::STATUS_RESOLVED, PanicAlert::STATUS_FALSE_ALARM], true)) {
            return response()->json(['success' => true, 'ignored' => 'closed']);
        }

        $newAcc = $validated['location_accuracy_m'] ?? null;
        $oldAcc = $alert->location_accuracy_m;

        // Elimizde hiç konum yoksa her zaman yaz. Varsa: yeni fix daha iyi (veya eşit)
        // doğruluktaysa ya da doğruluk bilinmiyorsa güncelle; belirgin şekilde kötüyse ezme.
        $shouldUpdate = $alert->lat === null
            || $newAcc === null
            || $oldAcc === null
            || (float) $newAcc <= (float) $oldAcc + 25; // 25 m tolerans (hareket + jitter)

        if ($shouldUpdate) {
            $alert->update([
                'lat'                 => $validated['lat'],
                'lng'                 => $validated['lng'],
                'location_accuracy_m' => $newAcc ?? $oldAcc,
            ]);
        }

        return response()->json(['success' => true]);
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
     * Santral etkinse, panik anında operatörü otomatik arayıp kişiye köprüler.
     * Kapalıysa sessizce atlar (operatör panelden manuel de arayabilir).
     */
    protected function autoCall(PanicAlert $alert): void
    {
        if (! config('services.panic.click_to_call.enabled', false)) {
            return;
        }
        if (! $alert->triggered_by_phone) {
            return;
        }
        try {
            app(ClickToCallService::class)->callToOperator($alert->triggered_by_phone);
        } catch (\Throwable $e) {
            Log::error('[Panic] Otomatik click-to-call istisna: ' . $e->getMessage(), [
                'alert_id' => $alert->public_id,
            ]);
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

    /**
     * POST /admin/panic-call
     * Operatör panelden click-to-call başlatır (santral üzerinden).
     * Body: { alert_id: int }
     * Santral kurulu değilse success:false döner → arayüz tel: fallback yapar.
     */
    public function call(Request $request, ClickToCallService $ctc): JsonResponse
    {
        $validated = $request->validate([
            'alert_id' => ['required', 'integer'],
        ]);

        $alert = PanicAlert::find($validated['alert_id']);
        if (! $alert || ! $alert->triggered_by_phone) {
            return response()->json([
                'success' => false,
                'message' => 'Alarm veya telefon bulunamadı.',
            ], 422);
        }

        $res = $ctc->callToOperator($alert->triggered_by_phone);

        return response()->json([
            'success' => $res['ok'],
            'message' => $res['message'] ?? ($res['ok'] ? 'Çağrı başlatıldı.' : 'Çağrı başlatılamadı.'),
        ], $res['ok'] ? 200 : 422);
    }

    // ─────────────────────────────────────────────────────────
    // WebRTC sesli görüşme sinyalleşmesi (kişi ↔ destek çalışanı)
    // Kişi = arayan (offer), operatör = cevaplayan (answer). Ses P2P, sinyal polling.
    // ─────────────────────────────────────────────────────────

    private const SIGNAL_TTL_SECONDS = 90;

    /**
     * POST /api/panic/{publicId}/signal   (kişi tarafı — public_id ile yetki)
     * Body: { type: offer|answer|ice|bye, payload: {...} }
     */
    public function callerSignal(Request $request, string $publicId): JsonResponse
    {
        $alert = PanicAlert::where('public_id', $publicId)->firstOrFail();
        return $this->pushSignal($request, $alert, 'user');
    }

    /**
     * GET /api/panic/{publicId}/signals?since_id=N   (kişi tarafı)
     * Operatörün yolladığı sinyalleri çeker.
     */
    public function callerSignals(Request $request, string $publicId): JsonResponse
    {
        $alert = PanicAlert::where('public_id', $publicId)->firstOrFail();
        return $this->pullSignals($request, $alert, 'operator');
    }

    /**
     * POST /admin/panic-call/{id}/signal   (operatör — admin auth)
     */
    public function operatorSignal(Request $request, int $id): JsonResponse
    {
        $alert = PanicAlert::findOrFail($id);
        return $this->pushSignal($request, $alert, 'operator');
    }

    /**
     * GET /admin/panic-call/{id}/signals?since_id=N   (operatör — admin auth)
     * Kişinin yolladığı sinyalleri çeker.
     */
    public function operatorSignals(Request $request, int $id): JsonResponse
    {
        $alert = PanicAlert::findOrFail($id);
        return $this->pullSignals($request, $alert, 'user');
    }

    private function pushSignal(Request $request, PanicAlert $alert, string $fromRole): JsonResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'in:offer,answer,ice,bye'],
            'payload' => ['required', 'array'],
        ]);

        PanicCallSignal::create([
            'panic_alert_id' => $alert->id,
            'from_role'      => $fromRole,
            'type'           => $validated['type'],
            'payload'        => $validated['payload'],
            'created_at'     => now(),
        ]);

        return response()->json(['success' => true]);
    }

    private function pullSignals(Request $request, PanicAlert $alert, string $otherRole): JsonResponse
    {
        $sinceId = (int) $request->query('since_id', 0);

        $signals = PanicCallSignal::where('panic_alert_id', $alert->id)
            ->where('from_role', $otherRole)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'from_role', 'type', 'payload', 'created_at']);

        // Eski sinyalleri temizle
        PanicCallSignal::where('panic_alert_id', $alert->id)
            ->where('created_at', '<', now()->subSeconds(self::SIGNAL_TTL_SECONDS))
            ->delete();

        return response()->json([
            'success' => true,
            'signals' => $signals->map(fn ($s) => [
                'id'      => $s->id,
                'type'    => $s->type,
                'payload' => $s->payload,
            ])->values(),
        ]);
    }
}
