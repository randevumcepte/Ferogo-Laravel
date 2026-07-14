<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\DispatcherService;
use App\Modules\Booking\Services\NoShowService;
use App\Modules\Booking\Services\RideRequestService;
use App\Modules\Booking\Support\NegotiationPayload;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Mobil sürücü panel flow — web'deki DriverPanelController'ın Sanctum eşleniği.
 *
 * Auth: Bearer + X-Device-Id. Ability scope: driver:*
 */
class DriverController extends Controller
{
    use NegotiationPayload;

    public function __construct(
        private RideRequestService $service,
        private NoShowService $noShowService,
        private CustomerTrustService $trustService,
    ) {}

    /**
     * GET /api/v1/driver/state?since_id=N
     * Tek endpoint = az polling gürültüsü. Web tarafıyla aynı payload yapısı (mobile-friendly).
     */
    public function state(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false, 'message' => 'Sürücü bulunamadı.'], 404);

        // Aktif yolculuk (kabul ettiğim, ride'ı tamamlanmamış/iptal olmamış)
        $activeRequest = RideRequest::query()
            ->with(['acceptedDriver.user', 'ride'])
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->whereHas('ride', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->latest('accepted_at')
            ->first();

        // Yeni teklif sadece aktif yolculuk yokken (ve busy değilken)
        $offer = null;
        if (! $activeRequest && $driver->availability_status !== 'busy') {
            $offer = RideRequest::query()
                ->where('offered_driver_id', $driver->id)
                ->where('status', 'pending')
                ->where('offer_expires_at', '>', now())
                ->orderBy('created_at')
                ->first();
        }

        $messages = [];
        if ($activeRequest) {
            $sinceId  = (int) $request->query('since_id', 0);
            $messages = $activeRequest->messages()
                ->where('id', '>', $sinceId)
                ->limit(100)
                ->get(['id', 'sender', 'body', 'created_at'])
                ->map(fn ($m) => [
                    'id'         => $m->id,
                    'sender'     => $m->sender,
                    'body'       => $m->body,
                    'created_at' => $m->created_at->toIso8601String(),
                ])->all();
        }

        return response()->json([
            'ok'     => true,
            'driver' => [
                'id'                  => $driver->id,
                'name'                => $driver->user->name,
                'availability_status' => $driver->availability_status,
                'rating'              => (float) $driver->rating,
                'total_rides'         => (int) $driver->total_rides,
                'is_female'           => $driver->user->gender === 'female',
                'women_only'          => (bool) $driver->women_passengers_only,
                'service_radius_km'   => (float) ($driver->service_radius_km ?? 5.0),
            ],
            'offer'    => $offer ? $this->offerPayload($offer) : null,
            'active'   => $activeRequest ? $this->activeRequestPayload($activeRequest) : null,
            'messages' => $messages,
        ]);
    }

    /**
     * POST /api/v1/driver/availability
     * Body: { status: online|offline, lat?, lng? }
     */
    public function setAvailability(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['online', 'offline'])],
            'lat'    => ['nullable', 'numeric', 'between:-90,90'],
            'lng'    => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // busy aktif yolculuktan otomatik — GERÇEK aktif yolculuk varsa el ile
        // değiştirilemez. Ama aktif talep yoksa (iptal/çökme sonrası kilitli
        // kalmış) sürücü "Çevrimiçi ol" ile kendini kurtarabilir (self-heal).
        if ($driver->availability_status === 'busy') {
            $hasActive = $this->activeRequestFor($driver) !== null;
            if ($hasActive) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Aktif yolculuk varken durum değiştirilemez.',
                    'status'  => 'busy',
                ], 409);
            }
            // Kilitli busy → devam et, istenen duruma geç (kilidi aç).
        }

        $update = ['availability_status' => $validated['status']];
        if (isset($validated['lat'], $validated['lng'])) {
            $update['current_lat'] = (float) $validated['lat'];
            $update['current_lng'] = (float) $validated['lng'];
            $update['last_location_updated_at'] = now();
        }
        $driver->update($update);

        return response()->json(['ok' => true, 'status' => $driver->fresh()->availability_status]);
    }

    /**
     * POST /api/v1/driver/women-only
     * Body: { enabled: bool }
     * "Sadece kadın yolcu al" tercihi — yalnızca kadın sürücüler.
     */
    public function setWomenOnly(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        if ($driver->user?->gender !== 'female') {
            return response()->json([
                'ok'      => false,
                'message' => 'Bu özellik yalnızca kadın sürücüler içindir.',
            ], 403);
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $driver->update(['women_passengers_only' => $validated['enabled']]);

        return response()->json(['ok' => true, 'women_only' => (bool) $driver->fresh()->women_passengers_only]);
    }

    /**
     * POST /api/v1/driver/service-radius
     * Body: { radius_km: 2..20 }
     * Sürücünün görünürlük/hizmet çapı — yalnızca bu mesafedeki alış noktaları
     * için aday gösterilir/eşleşir ve yolcu radarında görünür.
     */
    public function setServiceRadius(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'radius_km' => ['required', 'numeric', 'min:2', 'max:20'],
        ]);

        // 0.5 km adımlarına yuvarla (slider ile uyumlu, temiz değer).
        $radius = round(((float) $validated['radius_km']) * 2) / 2;
        $driver->update(['service_radius_km' => $radius]);

        return response()->json([
            'ok'                => true,
            'service_radius_km' => (float) $driver->fresh()->service_radius_km,
        ]);
    }

    /**
     * POST /api/v1/driver/location
     * Body: { lat, lng }
     * Sürücü online iken arka planda periyodik konum güncellemesi (her 20-30 sn).
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Rate limit: konum 5 sn'den hızlı güncellenmesin (battery saver)
        $rl = 'driver_loc:' . $driver->id;
        if (RateLimiter::tooManyAttempts($rl, 1)) {
            return response()->json(['ok' => true, 'throttled' => true]);
        }
        RateLimiter::hit($rl, 5);

        $driver->update([
            'current_lat'         => (float) $validated['lat'],
            'current_lng'         => (float) $validated['lng'],
            'last_location_updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/driver/offers/{publicId}/accept
     */
    public function acceptOffer(Request $request, string $publicId): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        // Havuz teklifi ise dispatcher üzerinden (müşteri reconfirm akışı başlar)
        if ($req->status === 'pool_expanded') {
            $ok = app(DispatcherService::class)->acceptByPoolDriver($req, $driver);
            return response()->json(
                $ok
                    ? ['ok' => true, 'awaiting_customer_reconfirm' => true]
                    : ['ok' => false, 'message' => 'Bu talep artık geçerli değil.'],
                $ok ? 200 : 409
            );
        }

        // Sürücü YOLCUNUN teklifini kabul eder → o fiyattan anlaşma
        $agreed = $req->customer_offer_fare !== null ? (float) $req->customer_offer_fare : null;
        try {
            $this->service->accept($req, $driver, $agreed);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/driver/offers/{publicId}/counter
     * Body: { amount } — sürücü yolcunun teklifine karşı fiyat verir.
     */
    public function counterOffer(Request $request, string $publicId): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        // Havuz teklifi ise dispatcher üzerinden (ilk cevaplayan kilidi alır)
        if ($req->status === 'pool_expanded') {
            $ok = app(DispatcherService::class)->counterByPoolDriver($req, $driver, (float) $validated['amount']);
            return response()->json(
                $ok
                    ? ['ok' => true, 'awaiting_customer_reconfirm' => true]
                    : ['ok' => false, 'message' => 'Bu talep artık geçerli değil.'],
                $ok ? 200 : 409
            );
        }

        $result = $this->service->driverCounter($req, $driver, (float) $validated['amount']);
        return response()->json(array_merge(['ok' => $result['ok']], $result), $result['ok'] ? 200 : 422);
    }

    /**
     * POST /api/v1/driver/offers/{publicId}/reject
     */
    public function rejectOffer(Request $request, string $publicId): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        $this->service->reject($req, $driver);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/driver/active/arrived
     */
    public function markArrived(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $req = $this->activeRequestFor($driver);
        if (! $req) return response()->json(['ok' => false, 'message' => 'Aktif yolculuk yok.'], 404);

        $result = $this->noShowService->markDriverArrived($req, $driver);
        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * POST /api/v1/driver/active/start-code
     * Body: { code: "1234" }
     * Sürücü buluşmada yolcunun gösterdiği 4 haneli eşleşme kodunu girer.
     * Doğruysa yolculuk başlar (started_at + ride.status=in_progress).
     */
    public function startWithCode(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        $req = $this->activeRequestFor($driver);
        if (! $req) return response()->json(['ok' => false, 'message' => 'Aktif yolculuk yok.'], 404);

        // Zaten başladıysa idempotent
        if ($req->started_at !== null) {
            return response()->json(['ok' => true, 'started' => true]);
        }

        // Rate limit: 5 yanlış deneme / dk (brute-force koruması)
        $rl = 'driver_startcode:' . $driver->id;
        if (RateLimiter::tooManyAttempts($rl, 5)) {
            return response()->json(['ok' => false, 'message' => 'Çok fazla hatalı deneme. Biraz bekle.'], 429);
        }

        if ($req->match_code === null || ! hash_equals((string) $req->match_code, (string) $validated['code'])) {
            RateLimiter::hit($rl, 60);
            return response()->json(['ok' => false, 'message' => 'Eşleşme kodu hatalı. Yolcudan kodu tekrar iste.'], 422);
        }

        $now = now();
        $req->update(['started_at' => $now, 'driver_arrived_at' => $req->driver_arrived_at ?? $now]);
        if ($req->ride) {
            $req->ride->update(['status' => 'in_progress']);
        }

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Eşleşme kodu doğrulandı. Yolculuk başladı.',
        ]);

        return response()->json(['ok' => true, 'started' => true]);
    }

    /**
     * POST /api/v1/driver/active/no-show
     * Body: { lat?, lng?, note? }
     */
    public function reportNoShow(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'lat'  => ['nullable', 'numeric', 'between:-90,90'],
            'lng'  => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $req = $this->activeRequestFor($driver);
        if (! $req) return response()->json(['ok' => false, 'message' => 'Aktif yolculuk yok.'], 404);

        $result = $this->noShowService->reportNoShow(
            $req,
            $driver,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
            $validated['note'] ?? null,
        );

        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * POST /api/v1/driver/active/complete
     */
    public function completeRide(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $req = $this->activeRequestFor($driver);
        if (! $req) return response()->json(['ok' => false, 'message' => 'Aktif yolculuk yok.'], 404);

        if ($req->ride) {
            $req->ride->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }
        $driver->update(['availability_status' => 'online']);
        $driver->increment('total_rides');

        $this->trustService->recordRideCompleted($req->customer_phone);

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Yolculuk tamamlandı.',
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/driver/active/cancel
     * Sürücü aktif yolculuğu iptal eder / takılan durumu kapatır.
     * Talep + ride 'cancelled', sürücü tekrar 'online'.
     */
    public function cancelActive(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $req = $this->activeRequestFor($driver);
        if ($req) {
            $req->update(['status' => 'cancelled']);
            if ($req->ride) {
                $req->ride->update(['status' => 'cancelled']);
            }
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Sürücü yolculuğu iptal etti.',
            ]);
        }

        // Sürücüyü her hâlükârda serbest bırak (busy kilidini aç).
        $driver->update(['availability_status' => 'online']);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/driver/active/message
     * Body: { body }
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $driver = $this->currentDriver($request);
        if (! $driver) return response()->json(['ok' => false], 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $req = $this->activeRequestFor($driver);
        if (! $req) return response()->json(['ok' => false, 'message' => 'Aktif yolculuk yok.'], 404);

        // Rate limit: 10 mesaj / dk
        $rl = 'driver_msg:' . $driver->id;
        if (RateLimiter::tooManyAttempts($rl, 10)) {
            return response()->json(['ok' => false, 'message' => 'Çok hızlı yazıyorsun.'], 429);
        }
        RateLimiter::hit($rl, 60);

        $msg = RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'driver',
            'body'            => $validated['body'],
        ]);

        // Müşteriye "yeni mesaj" push (best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->newMessage($req, 'driver', $validated['body']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Driver] message push', ['err' => $e->getMessage()]);
        }

        return response()->json([
            'ok'      => true,
            'message' => [
                'id'         => $msg->id,
                'sender'     => $msg->sender,
                'body'       => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function currentDriver(Request $request): ?Driver
    {
        $user = $request->user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }

    private function activeRequestFor(Driver $driver): ?RideRequest
    {
        return RideRequest::query()
            ->with('ride')
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->first();
    }

    private function offerPayload(RideRequest $req): array
    {
        return [
            'public_id'         => $req->public_id,
            'customer_name'     => $req->customer_name,
            'pickup_address'    => $req->pickup_address,
            'pickup_lat'        => (float) $req->pickup_lat,
            'pickup_lng'        => (float) $req->pickup_lng,
            'dropoff_address'   => $req->dropoff_address,
            'distance_km'       => (float) $req->distance_km,
            'duration_minutes'  => (int) $req->duration_minutes,
            'estimated_fare'    => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'expires_at'        => $req->offer_expires_at?->toIso8601String(),
            'seconds_remaining' => max(0, (int) round(now()->diffInSeconds($req->offer_expires_at, false))),
            // Fiyat pazarlığı — sürücü yolcunun teklifini görür, counter atabilir
            'negotiation'       => $this->negotiationPayload($req),
        ];
    }

    private function activeRequestPayload(RideRequest $req): array
    {
        $trust = $this->trustService->getOrCreate($req->customer_phone);

        $arrivedAt = $req->driver_arrived_at;
        $waitSec   = $arrivedAt ? abs((int) $arrivedAt->diffInSeconds(now())) : 0;
        $noShowReady = $arrivedAt && $waitSec >= NoShowService::MIN_WAIT_SECONDS;
        $noShowCountdown = $arrivedAt ? max(0, NoShowService::MIN_WAIT_SECONDS - $waitSec) : null;

        return [
            'public_id'                => $req->public_id,
            'customer_name'            => $req->customer_name,
            'customer_phone'           => $req->customer_phone,
            'customer_trust_label'     => $trust->trustLabel(),
            'customer_is_new'          => $trust->isNewCustomer(),
            'customer_completed_rides' => (int) $trust->total_completed,
            'customer_no_shows'        => (int) $trust->total_no_shows,
            'pickup_address'           => $req->pickup_address,
            'pickup_lat'               => (float) $req->pickup_lat,
            'pickup_lng'               => (float) $req->pickup_lng,
            'dropoff_address'          => $req->dropoff_address,
            'dropoff_lat'              => $req->dropoff_lat !== null ? (float) $req->dropoff_lat : null,
            'dropoff_lng'              => $req->dropoff_lng !== null ? (float) $req->dropoff_lng : null,
            'distance_km'              => (float) $req->distance_km,
            'duration_minutes'         => (int) $req->duration_minutes,
            'estimated_fare'           => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'accepted_at'              => $req->accepted_at?->toIso8601String(),
            'arrived_at'               => $arrivedAt?->toIso8601String(),
            'customer_confirmed_at'    => $req->customer_confirmed_at?->toIso8601String(),
            'no_show_button_ready'     => $noShowReady,
            'no_show_countdown_sec'    => $noShowCountdown,
            'ride_status'              => $req->ride?->status,
            // Eşleşme kodu akışı — kod YOLCUDA; sürücü girerek yolculuğu başlatır.
            'needs_start_code'         => $req->started_at === null,
            'started_at'               => $req->started_at?->toIso8601String(),
        ];
    }
}
