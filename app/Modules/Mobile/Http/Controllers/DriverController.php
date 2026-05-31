<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\NoShowService;
use App\Modules\Booking\Services\RideRequestService;
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

        // busy aktif yolculuktan otomatik — sürücü el ile değiştiremez
        if ($driver->availability_status === 'busy') {
            return response()->json([
                'ok'      => false,
                'message' => 'Aktif yolculuk varken durum değiştirilemez.',
                'status'  => 'busy',
            ], 409);
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

        try {
            $this->service->accept($req, $driver);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
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
            'distance_km'              => (float) $req->distance_km,
            'duration_minutes'         => (int) $req->duration_minutes,
            'estimated_fare'           => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'accepted_at'              => $req->accepted_at?->toIso8601String(),
            'arrived_at'               => $arrivedAt?->toIso8601String(),
            'customer_confirmed_at'    => $req->customer_confirmed_at?->toIso8601String(),
            'no_show_button_ready'     => $noShowReady,
            'no_show_countdown_sec'    => $noShowCountdown,
            'ride_status'              => $req->ride?->status,
        ];
    }
}
