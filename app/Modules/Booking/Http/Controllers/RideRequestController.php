<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\NoShowService;
use App\Modules\Booking\Services\PhoneVerificationService;
use App\Modules\Booking\Services\RideRequestService;
use App\Modules\Driver\Models\Driver;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class RideRequestController extends Controller
{
    public function __construct(
        private RideRequestService $service,
        private CustomerTrustService $trustService,
        private PhoneVerificationService $verificationService,
        private NoShowService $noShowService,
    ) {}

    /**
     * GET /api/ride-requests/nearby — kullanıcıya en yakın N müsait sürücü.
     * Müşteri modal'ı bunu çağırır; "Seç" tıkladığında gerçek driver_id'leri
     * preferred + fallback olarak store()'a yollar.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat'   => ['required', 'numeric', 'between:-90,90'],
            'lng'   => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $lat    = (float) $validated['lat'];
        $lng    = (float) $validated['lng'];
        $limit  = (int) ($validated['limit'] ?? 3);

        // Tüm müsait sürücüleri çek (bbox YOK — demo aşamasında her şehirden
        // test edilebilsin diye; üretimde driver sayısı çoğalınca bbox ekleriz)
        $candidates = Driver::query()
            ->with(['user:id,name', 'currentVehicle.vehicleClass'])
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->limit(50)
            ->get();

        $scored = $candidates->map(function (Driver $d) use ($lat, $lng) {
            $km = $this->haversineKm($lat, $lng, (float) $d->current_lat, (float) $d->current_lng);
            $payload = $this->driverPayload($d);
            return array_merge($payload ?? [], [
                'distance_km' => round($km, 2),
                'eta_minutes' => max(1, (int) round($km * 2.4 + 0.8)),
            ]);
        })->sortBy('distance_km')->take($limit)->values();

        // Diagnostic counts — frontend hatalı mesaj göstermek için kullanabilir
        $totalOnline = Driver::query()
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->count();

        return response()->json([
            'success'       => true,
            'drivers'       => $scored,
            'total_online'  => $totalOnline,
        ]);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earthKm * asin(min(1.0, sqrt($a)));
    }

    /**
     * POST /api/ride-requests — yeni talep yarat.
     * Müşteri radar üzerinden bir sürücü seçti, modal'ı gönderdi.
     * En yakın 3 sürücü (seçilen önde) aday kuyruğuna yazılır.
     */
    public function store(Request $request): JsonResponse
    {
        $vehicleClassSlugs = VehicleClass::where('is_active', true)->pluck('slug')->toArray();

        $validated = $request->validate([
            'vehicle_class_slug'    => ['required', Rule::in($vehicleClassSlugs)],
            'pickup_address'        => ['required', 'string', 'max:255'],
            'pickup_lat'            => ['required', 'numeric'],
            'pickup_lng'            => ['required', 'numeric'],
            'dropoff_address'       => ['required', 'string', 'max:255'],
            'dropoff_lat'           => ['nullable', 'numeric'],
            'dropoff_lng'           => ['nullable', 'numeric'],
            'customer_name'         => ['required', 'string', 'max:120'],
            'customer_phone'        => ['required', 'string', 'max:20'],
            'verification_token'    => ['required', 'string', 'size:48'],
            'fingerprint'           => ['nullable', 'string', 'max:64'],
            'distance_km'           => ['required', 'numeric', 'min:0', 'max:500'],
            'duration_minutes'      => ['required', 'integer', 'min:1', 'max:600'],
            'estimated_fare'        => ['nullable', 'numeric', 'min:0'],
            'preferred_driver_id'   => ['required', 'integer', 'exists:drivers,id'],
            'fallback_driver_ids'   => ['nullable', 'array', 'max:5'],
            'fallback_driver_ids.*' => ['integer', 'exists:drivers,id'],
            'kvkk_consent'          => ['required', 'accepted'],
        ], [
            'kvkk_consent.accepted'       => 'KVKK onayını işaretlemen gerekiyor.',
            'verification_token.required' => 'Telefonunu doğrulaman gerekiyor. SMS kodunu gir.',
        ]);

        $ip          = $request->ip();
        $fingerprint = $validated['fingerprint'] ?? null;

        // ─── KORUMA KATMANI ─────────────────────────────────────
        // 1) Trust + ban kontrolü
        $trustCheck = $this->trustService->canRequestRide(
            $validated['customer_phone'], $ip, $fingerprint,
        );
        if (! $trustCheck['ok']) {
            return response()->json([
                'success' => false,
                'message' => $trustCheck['reason'],
                'retry_after' => $trustCheck['retry_after'] ?? null,
            ], 429);
        }

        // 2) OTP token doğrulama
        $tokenCheck = $this->verificationService->validateToken(
            $validated['customer_phone'], $validated['verification_token'],
        );
        if (! $tokenCheck['ok']) {
            return response()->json([
                'success'              => false,
                'message'              => $tokenCheck['message'],
                'phone_reverify_required' => true,
            ], 422);
        }

        // 3) Per-phone rate limit: 10 dakikada max 2 aktif/yeni talep
        $phoneNorm = $this->trustService->normalizePhone($validated['customer_phone']);
        $phoneKey  = 'rr_create_phone:' . $phoneNorm;
        if (RateLimiter::tooManyAttempts($phoneKey, 2)) {
            return response()->json([
                'success'     => false,
                'message'     => 'Çok hızlı talep gönderiyorsun. Bir önceki çağrını tamamla veya birkaç dakika bekle.',
                'retry_after' => RateLimiter::availableIn($phoneKey),
            ], 429);
        }

        // 4) Per-IP rate limit: 10 dakikada max 5 yeni talep (farklı telefonlar olsa bile)
        $ipKey = 'rr_create_ip:' . $ip;
        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            return response()->json([
                'success'     => false,
                'message'     => 'Bu cihazdan çok fazla talep. Daha sonra dene.',
                'retry_after' => RateLimiter::availableIn($ipKey),
            ], 429);
        }

        // 5) Fingerprint: aynı tarayıcıdan saatte max 8 talep
        if ($fingerprint) {
            $fpKey = 'rr_create_fp:' . $fingerprint;
            if (RateLimiter::tooManyAttempts($fpKey, 8)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu cihaz limiti aştı. 1 saat sonra dene.',
                ], 429);
            }
            RateLimiter::hit($fpKey, 3600);
        }

        RateLimiter::hit($phoneKey, 600);
        RateLimiter::hit($ipKey, 600);
        // ─── /KORUMA KATMANI ────────────────────────────────────

        $vehicleClass = VehicleClass::where('slug', $validated['vehicle_class_slug'])->firstOrFail();

        $candidates = array_unique(array_merge(
            [(int) $validated['preferred_driver_id']],
            $validated['fallback_driver_ids'] ?? []
        ));

        // Sadece online + approved + müsait sürücüler aday olabilir
        $validCandidates = Driver::query()
            ->whereIn('id', $candidates)
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->pluck('id')
            ->all();

        // Sıralamayı koru (seçilen ilk sırada)
        $orderedCandidates = array_values(array_filter(
            $candidates,
            fn ($id) => in_array($id, $validCandidates, true)
        ));

        if (empty($orderedCandidates)) {
            return response()->json([
                'success' => false,
                'message' => 'Seçtiğin sürücü şu an müsait değil. Sayfayı yenile, başka bir sürücü dene.',
            ], 422);
        }

        $req = $this->service->create([
            'customer_name'        => $validated['customer_name'],
            'customer_phone'       => $validated['customer_phone'],
            'vehicle_class_id'     => $vehicleClass->id,
            'pickup_address'       => $validated['pickup_address'],
            'pickup_lat'           => (float) $validated['pickup_lat'],
            'pickup_lng'           => (float) $validated['pickup_lng'],
            'dropoff_address'      => $validated['dropoff_address'],
            'dropoff_lat'          => isset($validated['dropoff_lat']) ? (float) $validated['dropoff_lat'] : null,
            'dropoff_lng'          => isset($validated['dropoff_lng']) ? (float) $validated['dropoff_lng'] : null,
            'distance_km'          => (float) $validated['distance_km'],
            'duration_minutes'     => (int) $validated['duration_minutes'],
            'estimated_fare'       => isset($validated['estimated_fare']) ? (float) $validated['estimated_fare'] : null,
            'candidate_driver_ids' => $orderedCandidates,
            'phone_verified_at'    => now(),
            'verification_token'   => $validated['verification_token'],
            'client_ip'            => $ip,
            'client_fingerprint'   => $fingerprint,
            'user_agent'           => substr((string) $request->userAgent(), 0, 500),
        ]);

        // Trust kaydı
        $this->trustService->recordRequestCreated(
            $validated['customer_phone'], $ip, $fingerprint,
        );

        return response()->json([
            'success'   => true,
            'public_id' => $req->public_id,
            'status'    => $this->statusPayload($req),
        ]);
    }

    /**
     * GET /api/ride-requests/{publicId} — müşteri durumu sorgular (her 2 sn'de).
     * Süresi dolmuş bir teklif varsa burada self-heal eder.
     */
    public function show(string $publicId): JsonResponse
    {
        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        $req = $this->service->tickExpiry($req);

        return response()->json([
            'success' => true,
            'status'  => $this->statusPayload($req),
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/cancel — müşteri vazgeçer.
     */
    public function cancel(string $publicId): JsonResponse
    {
        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        $req = $this->service->cancelByCustomer($req);

        return response()->json([
            'success' => true,
            'status'  => $this->statusPayload($req),
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/confirm — müşteri sürücüyü gördüğünü onaylar.
     * Sürücüye "müşteri gerçekten orada" sinyali — bot/rakip kontrolü.
     */
    public function confirm(string $publicId): JsonResponse
    {
        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        $result = $this->noShowService->customerConfirm($req);
        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * GET /api/ride-requests/{publicId}/messages — chat poll.
     */
    public function messages(Request $request, string $publicId): JsonResponse
    {
        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        $sinceId = (int) $request->query('since_id', 0);

        $messages = $req->messages()
            ->where('id', '>', $sinceId)
            ->limit(100)
            ->get(['id', 'sender', 'body', 'created_at']);

        return response()->json([
            'success'  => true,
            'messages' => $messages->map(fn ($m) => [
                'id'         => $m->id,
                'sender'     => $m->sender,
                'body'       => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/messages — müşteri sürücüye mesaj atar.
     * Yalnızca accepted state'inde aktif.
     */
    public function sendMessage(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        if (! $req->isAccepted()) {
            return response()->json([
                'success' => false,
                'message' => 'Mesajlaşma yolculuk başlayınca aktif olur.',
            ], 422);
        }

        $msg = RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'customer',
            'body'            => $validated['body'],
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id'         => $msg->id,
                'sender'     => $msg->sender,
                'body'       => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Status payload — müşteri tarafı bu objeyi parse eder.
     */
    private function statusPayload(RideRequest $req): array
    {
        $payload = [
            'status'                => $req->status,
            'rejection_count'       => (int) $req->rejection_count,
            'current_index'         => (int) $req->current_candidate_index,
            'total_candidates'      => count($req->candidate_driver_ids ?? []),
            'seconds_remaining'     => $req->status === 'pending'
                ? max(0, (int) round(now()->diffInSeconds($req->offer_expires_at, false)))
                : 0,
            'offered_driver'        => null,
            'accepted_driver'       => null,
            'ride_public_id'        => $req->ride?->public_id,
            'arrived_at'            => $req->driver_arrived_at?->toIso8601String(),
            'customer_confirmed_at' => $req->customer_confirmed_at?->toIso8601String(),
            'no_show_at'            => $req->no_show_at?->toIso8601String(),
        ];

        if ($req->offered_driver_id) {
            $req->loadMissing(['offeredDriver.user:id,name', 'offeredDriver.currentVehicle.vehicleClass']);
            $payload['offered_driver'] = $this->driverPayload($req->offeredDriver);
        }
        if ($req->accepted_driver_id) {
            $req->loadMissing(['acceptedDriver.user:id,name', 'acceptedDriver.currentVehicle.vehicleClass']);
            $payload['accepted_driver'] = $this->driverPayload($req->acceptedDriver);
        }

        return $payload;
    }

    private function driverPayload(?Driver $d): ?array
    {
        if (! $d) return null;

        $fullName = $d->user?->name ?? 'Sürücü';
        $parts = preg_split('/\s+/', trim($fullName));
        $shortName = count($parts) > 1
            ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $fullName;

        $v = $d->currentVehicle;
        $vClass = $v?->vehicleClass;

        return [
            'id'                  => $d->id,
            'name'                => $shortName,
            'rating'              => (float) $d->rating,
            'trips'               => (int) $d->total_rides,
            'vehicle_class'       => $vClass?->name,
            'vehicle_class_slug'  => $vClass?->slug,
            'vehicle_label'       => $v ? trim(($v->brand ?? '') . ' ' . ($v->model ?? '')) : null,
            'plate'               => $v?->plate,
        ];
    }
}
