<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\DispatcherService;
use App\Modules\Booking\Services\FavoriteDriverService;
use App\Modules\Booking\Services\NoShowService;
use App\Modules\Booking\Services\RideRequestService;
use App\Modules\Booking\Support\NegotiationPayload;
use App\Modules\Driver\Models\Driver;
use App\Modules\Pricing\Services\FareCalculator;
use App\Modules\Vehicle\Models\VehicleClass;
use App\Services\Geo\GeoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Mobil müşteri ride flow.
 *
 * Web tarafındaki RideRequestController + ReservationController'ın ilgili metodlarını
 * Sanctum'lu + mobile-friendly payload ile sunar.
 *
 * Auth: Bearer + X-Device-Id. Token ability: customer:* (route grup düzeyinde kontrol).
 */
class CustomerRideController extends Controller
{
    use NegotiationPayload;

    public function __construct(
        private RideRequestService $service,
        private CustomerTrustService $trustService,
        private NoShowService $noShowService,
        private FareCalculator $calculator,
        private FavoriteDriverService $favoriteService,
        private DispatcherService $dispatcher,
        private GeoService $geo,
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  REFERANS LİSTELER + YER ARAMA + FİYAT
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/customer/bootstrap
     * Uygulama açılır açılmaz lazım olan referans datası (vehicle class'lar vb).
     */
    public function bootstrap(): JsonResponse
    {
        $classes = VehicleClass::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'description'])
            ->map(fn ($c) => [
                'id'          => $c->id,
                'slug'        => $c->slug,
                'name'        => $c->name,
                'description' => $c->description,
            ]);

        return response()->json([
            'ok'             => true,
            'vehicle_classes'=> $classes,
            'server_time'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/customer/places/search?q=...
     * Yer arama (autocomplete). Tüm sağlayıcı mantığı GeoService'te:
     * Yandex Geosuggest (anahtar varsa) → Photon (OSM) → Nominatim. 60 dk cache servis içinde.
     *
     * Dönen öğe: { display_name, lat|null, lon|null, uri|null, provider }.
     * Yandex önerileri koordinatsız gelir (uri dolu) → uygulama seçince
     * /customer/places/resolve ile koordinat alır.
     */
    public function searchPlaces(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        // Per-user rate limit: 1 dakikada 30 arama
        $rl = 'places_search:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($rl, 30)) {
            return response()->json([
                'ok'          => false,
                'message'     => 'Çok hızlı arıyorsun, bir saniye bekle.',
                'retry_after' => RateLimiter::availableIn($rl),
            ], 429);
        }
        RateLimiter::hit($rl, 60);

        return response()->json([
            'ok'      => true,
            'results' => $this->geo->suggest($validated['q']),
        ]);
    }

    /**
     * GET /api/v1/customer/places/resolve?uri=...&text=...
     * Seçilen önerinin koordinatı. Yandex önerisi için uri, düz metin için text gönderilir.
     * Dönen: { ok, lat, lon, display_name } veya 404.
     */
    public function resolvePlace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uri'  => ['nullable', 'string', 'max:2000'],
            'text' => ['nullable', 'string', 'max:250'],
        ]);

        if (empty($validated['uri']) && empty($validated['text'])) {
            return response()->json(['ok' => false, 'message' => 'uri veya text gerekli'], 422);
        }

        $res = $this->geo->resolve($validated['uri'] ?? null, $validated['text'] ?? null);
        if ($res === null) {
            return response()->json(['ok' => false, 'message' => 'Konum çözümlenemedi'], 404);
        }

        return response()->json([
            'ok'           => true,
            'lat'          => $res['lat'],
            'lon'          => $res['lon'],
            'display_name' => $res['display_name'],
        ]);
    }

    /**
     * POST /api/v1/customer/fare/calculate
     * Body: { vehicle_class_id, distance_km, duration_minutes, scheduled_at?, extras? }
     * city_id mobilde gerekmiyor (İzmir default), web parite için body'de optional.
     */
    public function calculateFare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id'          => ['nullable', 'integer', 'exists:cities,id'],
            // Tek-kademe model: sınıf opsiyonel; boşsa aktif sınıf kullanılır.
            'vehicle_class_id' => ['nullable', 'integer', 'exists:vehicle_classes,id'],
            'distance_km'      => ['required', 'numeric', 'min:0', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:4320'],
            'scheduled_at'     => ['nullable', 'date'],
            'extras'           => ['nullable', 'array'],
            'extras.*.extra_id'=> ['integer', 'exists:extras,id'],
            'extras.*.quantity'=> ['integer', 'min:1', 'max:10'],
        ]);

        $user = $request->user();
        $tier = $this->calculator->resolveTierForPhone($user->phone);
        $scheduled = ! empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        $cityId = (int) ($validated['city_id'] ?? \App\Modules\Shared\Models\City::where('is_active', true)
            ->orderBy('sort_order')->value('id'));

        $vehicleClassId = ! empty($validated['vehicle_class_id'])
            ? (int) $validated['vehicle_class_id']
            : (int) VehicleClass::activeDefault()?->id;

        $fare = $this->calculator->calculate(
            cityId:           $cityId,
            vehicleClassId:   $vehicleClassId,
            distanceKm:       (float) $validated['distance_km'],
            durationMinutes:  (int) $validated['duration_minutes'],
            extras:           $validated['extras'] ?? [],
            scheduledAt:      $scheduled,
            customerTrustTier:$tier,
        );

        return response()->json(['ok' => true, 'fare' => $fare]);
    }

    // ─────────────────────────────────────────────────────────────
    //  YAKINDAKİ SÜRÜCÜLER + PROFİL
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/customer/drivers/nearby?lat=...&lng=...&limit=3
     */
    public function nearbyDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat'   => ['required', 'numeric', 'between:-90,90'],
            'lng'   => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $lat   = (float) $validated['lat'];
        $lng   = (float) $validated['lng'];
        $limit = (int) ($validated['limit'] ?? 3);

        $candidates = Driver::query()
            ->with(['user:id,name,avatar,gender', 'currentVehicle.vehicleClass'])
            ->withCount('favoritedByUsers as favorite_count')
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->limit(50)
            ->get();

        $favoriteIds = $this->favoriteService->favoriteIds($request->user());

        $scored = $candidates->map(function (Driver $d) use ($lat, $lng, $favoriteIds) {
            $km = $this->haversineKm($lat, $lng, (float) $d->current_lat, (float) $d->current_lng);
            $radius = (float) ($d->service_radius_km ?? 5.0);
            return array_merge($this->driverShortPayload($d), [
                'distance_km' => round($km, 2),
                'eta_minutes' => max(1, (int) round($km * 2.4 + 0.8)),
                'is_favorite' => in_array((int) $d->id, $favoriteIds, true),
                // Sürücü yalnızca kendi görünürlük çapı içindeki yolculara çıkar.
                '_within'     => $km <= $radius,
            ]);
        })
            ->filter(fn ($x) => $x['_within'])
            ->sortBy('distance_km')
            ->take($limit)
            ->map(function ($x) {
                unset($x['_within']);
                return $x;
            })
            ->values();

        $totalOnline = Driver::query()
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->count();

        return response()->json([
            'ok'            => true,
            'drivers'       => $scored,
            'total_online'  => $totalOnline,
        ]);
    }

    /**
     * GET /api/v1/customer/drivers/{id}/profile
     * Web'deki driverProfile metoduyla aynı şekil — mobil payload identical.
     */
    public function driverProfile(int $driverId): JsonResponse
    {
        $driver = Driver::query()
            ->with(['user', 'currentVehicle.vehicleClass'])
            ->withCount('favoritedByUsers as favorite_count')
            ->where('approval_status', 'approved')
            ->find($driverId);

        if (! $driver) {
            return response()->json(['ok' => false, 'message' => 'Sürücü bulunamadı.'], 404);
        }

        // Web RideRequestController::driverProfile içeriği aynısı — DRY ihlali ama
        // mobil payload'ı stabil tutmak için bağımsızlaştırılıyor.
        $user    = $driver->user;
        $vehicle = $driver->currentVehicle;
        $vClass  = $vehicle?->vehicleClass;

        $yearsDriving = $driver->license_issued_at
            ? (int) $driver->license_issued_at->diffInYears(now())
            : null;

        $expBandLabels = [
            'under_1' => '1 yıldan az',
            '1_to_3'  => '1-3 yıl',
            '3_to_5'  => '3-5 yıl',
            '5_plus'  => '5+ yıl',
        ];

        $credentials = [
            ['key' => 'approved',       'label' => 'Onaylı Sürücü', 'valid' => $driver->approval_status === 'approved'],
            ['key' => 'license',        'label' => 'Ehliyet',       'valid' => $driver->license_expires_at === null || $driver->license_expires_at->isFuture()],
            ['key' => 'src',            'label' => 'SRC',           'valid' => $driver->src_certificate_number && ($driver->src_expires_at === null || $driver->src_expires_at->isFuture())],
            ['key' => 'psychotechnic',  'label' => 'Psikoteknik',   'valid' => $driver->psychotechnic_test_at && $driver->psychotechnic_test_at->isAfter(now()->subYears(5))],
            ['key' => 'criminal_record','label' => 'Adli Sicil',    'valid' => $driver->criminal_record_at && $driver->criminal_record_at->isAfter(now()->subYears(2))],
        ];

        $vehiclePayload = null;
        if ($vehicle) {
            $features = array_values(array_filter([
                $vehicle->has_baby_seat    ? ['key' => 'baby_seat',    'label' => 'Bebek koltuğu'] : null,
                $vehicle->has_child_seat   ? ['key' => 'child_seat',   'label' => 'Çocuk koltuğu'] : null,
                $vehicle->has_booster_seat ? ['key' => 'booster_seat', 'label' => 'Yükseltici']    : null,
                $vehicle->pet_friendly     ? ['key' => 'pet_friendly', 'label' => 'Evcil hayvan']  : null,
            ]));

            $photos    = is_array($vehicle->photos) ? array_values(array_filter($vehicle->photos)) : [];
            $photoUrls = array_map(fn ($p) => str_starts_with($p, 'http') ? $p : asset('storage/' . ltrim($p, '/')), $photos);

            $vehiclePayload = [
                'brand'             => $vehicle->brand,
                'model'             => $vehicle->model,
                'year'              => $vehicle->year_of_manufacture,
                'color'             => $vehicle->color,
                'plate'             => $vehicle->plate,
                'class_name'        => $vClass?->name,
                'class_slug'        => $vClass?->slug,
                'photos'            => $photoUrls,
                'features'          => $features,
                'insurance_valid'   => $vehicle->insurance_expires_at === null || $vehicle->insurance_expires_at->isFuture(),
                'inspection_valid'  => $vehicle->inspection_expires_at === null || $vehicle->inspection_expires_at->isFuture(),
            ];
        }

        $fullName  = $user?->name ?? 'Sürücü';
        $parts     = preg_split('/\s+/', trim($fullName));
        $shortName = count($parts) > 1
            ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $fullName;

        $avatarUrl = null;
        if ($user?->avatar) {
            $avatarUrl = str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . ltrim($user->avatar, '/'));
        }

        return response()->json([
            'ok'     => true,
            'driver' => [
                'id'           => $driver->id,
                'name'         => $fullName,
                'short_name'   => $shortName,
                'avatar'       => $avatarUrl,
                'rating'       => (float) $driver->rating,
                'total_rides'  => (int) $driver->total_rides,
                'member_since' => $user?->created_at?->format('Y-m'),
                'experience'   => [
                    'band'          => $driver->experience_band,
                    'label'         => $expBandLabels[$driver->experience_band] ?? '—',
                    'years_driving' => $yearsDriving,
                ],
                'credentials'  => $credentials,
                'vehicle'      => $vehiclePayload,
                'is_favorite'  => $this->favoriteService->isFavorite(request()->user(), $driver->id),
                'favorite_count' => (int) ($driver->favorite_count ?? 0),
                'is_female'    => $user?->gender === 'female',
                'women_only'   => (bool) $driver->women_passengers_only,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  FAVORİ SÜRÜCÜLER ("tekrar onu çağır")
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/customer/favorites
     * Müşterinin favori sürücüleri + canlı müsaitlik (online ise hemen çağrılabilir).
     */
    public function favorites(Request $request): JsonResponse
    {
        $drivers = $this->favoriteService->listForUser($request->user());

        return response()->json([
            'ok'      => true,
            'drivers' => $drivers->map(fn (Driver $d) => array_merge($this->driverShortPayload($d), [
                'is_favorite'         => true,
                'is_online'           => $d->availability_status === 'online',
                'availability_status' => $d->availability_status,
            ]))->values(),
        ]);
    }

    /**
     * POST /api/v1/customer/favorites/{driverId} — favoriye ekle.
     */
    public function addFavorite(Request $request, int $driverId): JsonResponse
    {
        $result = $this->favoriteService->add($request->user(), $driverId);
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * DELETE /api/v1/customer/favorites/{driverId} — favoriden çıkar.
     */
    public function removeFavorite(Request $request, int $driverId): JsonResponse
    {
        $result = $this->favoriteService->remove($request->user(), $driverId);
        return response()->json($result, 200);
    }

    // ─────────────────────────────────────────────────────────────
    //  RIDE REQUEST CRUD
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/customer/ride-requests
     * Mobil müşteri yeni talep yaratır. Bearer authed olduğu için OTP token'a gerek yok.
     */
    public function storeRequest(Request $request): JsonResponse
    {
        $vehicleClassSlugs = VehicleClass::where('is_active', true)->pluck('slug')->toArray();
        $user = $request->user();

        // Dağıtım modu:
        //  'auto'   → favori-öncelikli dalga (tüm online favorilere), favori yoksa yakın havuz
        //  'nearby' → favorileri ATLA, doğrudan yakındaki (favori olmayan) havuza
        //  'manual' / null → yolcu bir favoriyi seçer (preferred_driver_id, 1:1 pazarlık)
        $dispatchMode = $request->input('dispatch_mode');
        $isAuto   = $dispatchMode === 'auto';    // favori-öncelikli + havuz (Tümü)
        $isNearby = $dispatchMode === 'nearby';  // favori olmayan yakın havuz
        $isList   = $dispatchMode === 'pool';    // seçili sürücü listesine (favori/havuz/kadın "hepsi")
        $isPool   = $isAuto || $isNearby || $isList;

        $validated = $request->validate([
            // Tek-kademe model: yolcu sınıf seçmez; boşsa sunucu aktif sınıfa düşer.
            'vehicle_class_slug'    => ['nullable', Rule::in($vehicleClassSlugs)],
            'pickup_address'        => ['required', 'string', 'max:255'],
            'pickup_lat'            => ['required', 'numeric'],
            'pickup_lng'            => ['required', 'numeric'],
            'dropoff_address'       => ['required', 'string', 'max:255'],
            'dropoff_lat'           => ['nullable', 'numeric'],
            'dropoff_lng'           => ['nullable', 'numeric'],
            'distance_km'           => ['required', 'numeric', 'min:0', 'max:2000'],
            'duration_minutes'      => ['required', 'integer', 'min:1', 'max:4320'],
            'estimated_fare'        => ['nullable', 'numeric', 'min:0'],
            'suggested_fare'        => ['nullable', 'numeric', 'min:0'],
            'customer_offer_fare'   => ['nullable', 'numeric', 'min:0'],
            'dispatch_mode'         => ['nullable', Rule::in(['auto', 'nearby', 'pool', 'manual'])],
            'preferred_driver_id'   => [$isPool ? 'nullable' : 'required', 'integer', 'exists:drivers,id'],
            'fallback_driver_ids'   => ['nullable', 'array', 'max:5'],
            'fallback_driver_ids.*' => ['integer', 'exists:drivers,id'],
            'driver_ids'            => [$isList ? 'required' : 'nullable', 'array', 'min:1', 'max:30'],
            'driver_ids.*'          => ['integer', 'exists:drivers,id'],
            'kvkk_consent'          => ['required', 'accepted'],
        ], [
            'kvkk_consent.accepted' => 'KVKK onayını işaretlemen gerekiyor.',
        ]);

        $deviceId = (string) $request->header('X-Device-Id', '');

        // Trust + ban + rate limit ortak koruması — web ile aynı katmanları çağırıyoruz
        $trustCheck = $this->trustService->canRequestRide($user->phone, $request->ip(), $deviceId);
        if (! $trustCheck['ok']) {
            return response()->json([
                'ok'          => false,
                'message'     => $trustCheck['reason'],
                'retry_after' => $trustCheck['retry_after'] ?? null,
            ], 429);
        }

        // Per-phone rate limit: 10 dk / 8 talep.
        // (Kademeli akış tek seferde 3 talep üretebilir: 1:1 → tüm favoriler → yakın;
        //  ayrıca ret/iptal sonrası yeniden deneme normal. Spam için 8 yeterince yüksek eşik.)
        $phoneNorm = $this->trustService->normalizePhone($user->phone);
        $phoneKey  = 'rr_create_phone:' . $phoneNorm;
        if (RateLimiter::tooManyAttempts($phoneKey, 8)) {
            $wait = RateLimiter::availableIn($phoneKey);
            return response()->json([
                'ok'          => false,
                'message'     => 'Kısa sürede çok fazla talep oluşturdun. ' . max(1, (int) ceil($wait / 60)) . ' dk sonra tekrar dene.',
                'retry_after' => $wait,
            ], 429);
        }

        // Per-IP rate limit: 10 dk / 15 talep
        $ipKey = 'rr_create_ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 15)) {
            return response()->json([
                'ok'          => false,
                'message'     => 'Bu ağdan çok fazla talep. Daha sonra dene.',
                'retry_after' => RateLimiter::availableIn($ipKey),
            ], 429);
        }

        RateLimiter::hit($phoneKey, 600);
        RateLimiter::hit($ipKey, 600);

        $vehicleClass = VehicleClass::where('slug', $validated['vehicle_class_slug'] ?? optional(VehicleClass::activeDefault())->slug)->firstOrFail();

        $customerIsFemale = $user->gender === 'female';

        // ─── AUTO MOD: favori-öncelikli dalga ("Hadi Gidelim") ──────
        // Yolcu sürücü seçmez; talep ÖNCE online favori sürücülere (mesafe sınırsız)
        // aynı anda gider. Favori yoksa/uygun değilse doğrudan yakındaki havuza.
        // Favori dalgası dönmezse cron (tickFavoriteWaves) yakına düşürür.
        // Auto mod SINIF-BAĞIMSIZ (class = null) — web parite.
        if ($isPool) {
            $onlineFavoriteIds = $this->favoriteService->dispatchableFavoriteIds(
                $user, null, $customerIsFemale,
            );

            if ($isAuto) {
                // Favori-öncelikli: tüm online favorilere; favori yoksa yakın havuz
                $favoriteIds = $onlineFavoriteIds;
                $isFavoriteWave = ! empty($favoriteIds);
                $poolIds = $isFavoriteWave
                    ? $favoriteIds
                    : $this->dispatcher->nearestDispatchableDriverIds(
                        (float) $validated['pickup_lat'],
                        (float) $validated['pickup_lng'],
                        null,
                    );
            } elseif ($isNearby) {
                // nearby: favorileri hariç tut → favori OLMAYAN yakın sürücüler
                $isFavoriteWave = false;
                $poolIds = $this->dispatcher->nearestDispatchableDriverIds(
                    (float) $validated['pickup_lat'],
                    (float) $validated['pickup_lng'],
                    null,
                    $onlineFavoriteIds,
                );
            } else {
                // pool: yolcunun seçtiği sürücü listesi (favori/havuz/kadın "hepsi") —
                // yalnızca online+onaylı+paketli olanlar; erkek yolcuya kadın-only gizli
                $isFavoriteWave = ! empty(array_intersect(
                    array_map('intval', $validated['driver_ids']),
                    $onlineFavoriteIds,
                ));
                $poolIds = Driver::query()
                    ->whereIn('id', array_map('intval', $validated['driver_ids']))
                    ->where('approval_status', 'approved')
                    ->where('availability_status', 'online')
                    ->when(config('services.driver.enforce_packages', true), fn ($q) => $q
                        ->whereNotNull('package_active_until')
                        ->where('package_active_until', '>', now()))
                    ->when(! $customerIsFemale, fn ($q) => $q->where('women_passengers_only', false))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            if (empty($poolIds)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Şu an uygun sürücü bulunamadı. Birazdan tekrar dene.',
                ], 422);
            }

            $req = $this->service->create([
                'customer_name'        => $user->name ?? 'Müşteri',
                'customer_phone'       => $user->phone,
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
                'suggested_fare'       => isset($validated['suggested_fare']) ? (float) $validated['suggested_fare'] : (isset($validated['estimated_fare']) ? (float) $validated['estimated_fare'] : null),
                'customer_offer_fare'  => isset($validated['customer_offer_fare']) ? (float) $validated['customer_offer_fare'] : null,
                'pool_driver_ids'      => $poolIds,
                'is_favorite_wave'     => $isFavoriteWave,
                'phone_verified_at'    => now(),
                'verification_token'   => null,
                'client_ip'            => $request->ip(),
                'client_fingerprint'   => $deviceId,
                'user_agent'           => substr((string) $request->userAgent(), 0, 500),
            ]);

            $this->trustService->recordRequestCreated($user->phone, $request->ip(), $deviceId);

            Log::info('[mobile-ride] auto_request_created', [
                'user_id'   => $user->id,
                'public_id' => $req->public_id,
                'dispatch'  => $isFavoriteWave ? 'favorites' : 'nearby',
                'pool'      => $poolIds,
            ]);

            return response()->json([
                'ok'        => true,
                'public_id' => $req->public_id,
                'dispatch'  => $isFavoriteWave ? 'favorites' : 'nearby',
                'status'    => $this->statusPayload($req),
            ]);
        }
        // ─── /AUTO MOD ──────────────────────────────────────────────

        // Aday sürücü listesi: preferred + fallback'lerden online+approved olanlar
        $candidates = array_unique(array_merge(
            [(int) $validated['preferred_driver_id']],
            $validated['fallback_driver_ids'] ?? []
        ));

        // Kadın sürücü güvenliği: "sadece kadın yolcu al" diyen sürücüler yalnızca
        // kadın müşterilere aday olabilir.
        $customerIsFemale = $user->gender === 'female';

        $validCandidates = Driver::query()
            ->whereIn('id', $candidates)
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->when(! $customerIsFemale, fn ($q) => $q->where('women_passengers_only', false))
            ->pluck('id')
            ->all();

        $orderedCandidates = array_values(array_filter(
            $candidates,
            fn ($id) => in_array($id, $validCandidates, true)
        ));

        if (empty($orderedCandidates)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Seçtiğin sürücü şu an müsait değil. Listeyi yenile, başkasını dene.',
            ], 422);
        }

        $req = $this->service->create([
            'customer_name'        => $user->name ?? 'Müşteri',
            'customer_phone'       => $user->phone,
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
            'suggested_fare'       => isset($validated['suggested_fare']) ? (float) $validated['suggested_fare'] : (isset($validated['estimated_fare']) ? (float) $validated['estimated_fare'] : null),
            'customer_offer_fare'  => isset($validated['customer_offer_fare']) ? (float) $validated['customer_offer_fare'] : null,
            'candidate_driver_ids' => $orderedCandidates,
            'phone_verified_at'    => now(),
            'verification_token'   => null,
            'client_ip'            => $request->ip(),
            'client_fingerprint'   => $deviceId,
            'user_agent'           => substr((string) $request->userAgent(), 0, 500),
        ]);

        $this->trustService->recordRequestCreated($user->phone, $request->ip(), $deviceId);

        Log::info('[mobile-ride] request_created', [
            'user_id'   => $user->id,
            'public_id' => $req->public_id,
            'candidates'=> $orderedCandidates,
        ]);

        return response()->json([
            'ok'        => true,
            'public_id' => $req->public_id,
            'status'    => $this->statusPayload($req),
        ]);
    }

    /**
     * GET /api/v1/customer/ride-requests/{publicId}
     */
    public function showRequest(string $publicId): JsonResponse
    {
        $req = $this->ownedRequest($publicId);
        $req = $this->service->tickExpiry($req);

        return response()->json([
            'ok'     => true,
            'status' => $this->statusPayload($req),
        ]);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/cancel
     */
    public function cancelRequest(string $publicId): JsonResponse
    {
        $req = $this->ownedRequest($publicId);
        $req = $this->service->cancelByCustomer($req);

        return response()->json([
            'ok'     => true,
            'status' => $this->statusPayload($req),
        ]);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/visual-verify
     * Body: { match: bool, note?: string }
     * Yolculuk başladıktan sonra yolcu "bindiğim araç/sürücü doğru mu?" cevabı verir.
     * match=true → doğrulandı; match=false → güvenlik uyarısı.
     */
    public function visualVerify(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'match' => ['required', 'boolean'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        $req = $this->ownedRequest($publicId);

        if ($req->started_at === null) {
            return response()->json(['ok' => false, 'message' => 'Yolculuk henüz başlamadı.'], 422);
        }
        // Zaten cevaplandıysa idempotent (tekrar modal açılmasın)
        if ($req->visual_verified_at !== null || $req->visual_verify_failed_at !== null) {
            return response()->json(['ok' => true, 'already' => true, 'status' => $this->statusPayload($req)]);
        }

        if ((bool) $validated['match']) {
            $req->update(['visual_verified_at' => now()]);
            $message = 'Teşekkürler, iyi yolculuklar!';
        } else {
            $req->update(['visual_verify_failed_at' => now()]);
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Yolcu araç/sürücü eşleşmediğini bildirdi. Güvenlik uyarısı oluşturuldu.',
            ]);
            $message = 'Bildirimin alındı. Güvenliğin için çağrı merkezimiz seninle iletişime geçebilir.';
        }

        return response()->json([
            'ok'       => true,
            'verified' => (bool) $validated['match'],
            'message'  => $message,
            'status'   => $this->statusPayload($req->fresh()),
        ]);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/counter
     * Body: { amount } — müşteri sürücünün karşı teklifine yeni fiyat verir.
     */
    public function counter(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $req    = $this->ownedRequest($publicId);
        $result = $this->service->customerCounter($req, (float) $validated['amount']);

        return response()->json(array_merge(['ok' => $result['ok']], $result), $result['ok'] ? 200 : 422);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/accept-price
     * Müşteri sürücünün karşı teklifini kabul eder → yolculuk başlar.
     */
    public function acceptPrice(string $publicId): JsonResponse
    {
        $req    = $this->ownedRequest($publicId);
        $result = $this->service->customerAcceptCounter($req);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'İşlem yapılamadı.'], 422);
        }

        return response()->json([
            'ok'     => true,
            'status' => $this->statusPayload($result['request']),
        ]);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/confirm
     */
    public function confirmRequest(string $publicId): JsonResponse
    {
        $req    = $this->ownedRequest($publicId);
        $result = $this->noShowService->customerConfirm($req);
        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/reconfirm
     * Body: { accept } — auto/havuz akışında eşleşen üye sürücüyü onayla (true) ya da reddet (false).
     */
    public function reconfirm(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'accept' => ['required', 'boolean'],
        ]);

        $req = $this->ownedRequest($publicId);

        if ($req->status !== 'awaiting_customer_reconfirm') {
            return response()->json([
                'ok'      => false,
                'message' => 'Bu talep şu an onay bekliyor değil.',
            ], 422);
        }

        try {
            $req = $this->dispatcher->customerReconfirm($req, (bool) $validated['accept']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Sürücü az önce müsait olmaktan çıktı. Tekrar dene.',
            ], 409);
        }

        return response()->json([
            'ok'     => true,
            'status' => $this->statusPayload($req),
        ]);
    }

    /**
     * GET /api/v1/customer/ride-requests/{publicId}/messages?since_id=
     */
    public function messages(Request $request, string $publicId): JsonResponse
    {
        $req     = $this->ownedRequest($publicId);
        $sinceId = (int) $request->query('since_id', 0);

        $messages = $req->messages()
            ->where('id', '>', $sinceId)
            ->limit(100)
            ->get(['id', 'sender', 'body', 'created_at'])
            ->map(fn ($m) => [
                'id'         => $m->id,
                'sender'     => $m->sender,
                'body'       => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'messages' => $messages]);
    }

    /**
     * POST /api/v1/customer/ride-requests/{publicId}/messages
     */
    public function sendMessage(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $req = $this->ownedRequest($publicId);

        if (! $req->isAccepted()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Mesajlaşma yolculuk başlayınca aktif olur.',
            ], 422);
        }

        // Per-user rate limit: 10 mesaj / dakika (abuse koruması)
        $rl = 'mobile_msg:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($rl, 10)) {
            return response()->json(['ok' => false, 'message' => 'Çok hızlı yazıyorsun.'], 429);
        }
        RateLimiter::hit($rl, 60);

        $msg = RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'customer',
            'body'            => $validated['body'],
        ]);

        // Sürücüye "yeni mesaj" push (best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->newMessage($req, 'customer', $validated['body']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[CustomerRide] message push', ['err' => $e->getMessage()]);
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

    /**
     * GET /api/v1/customer/history?limit=10
     * Son tamamlanmış/iptal yolculuklar.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = (int) min(50, max(1, (int) $request->query('limit', 10)));

        $rides = Ride::query()
            ->with(['driver.user:id,name', 'vehicleClass:id,name,slug'])
            ->where('customer_user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'ok'    => true,
            'rides' => $rides->map(fn (Ride $r) => [
                'public_id'        => $r->public_id,
                'status'           => $r->status,
                'pickup_address'   => $r->pickup_address,
                'dropoff_address'  => $r->dropoff_address,
                'distance_km'      => (float) $r->distance_km,
                'duration_minutes' => (int) $r->duration_minutes,
                'total_fare'       => $r->total_fare ? (float) $r->total_fare : null,
                'currency'         => $r->currency,
                'driver_name'      => $r->driver?->user?->name,
                'vehicle_class'    => $r->vehicleClass?->name,
                'completed_at'     => $r->completed_at?->toIso8601String(),
                'created_at'       => $r->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Bu request'i bu kullanıcının olduğunu doğrula.
     * Aksi halde 404 (varlık sızdırma yok).
     */
    private function ownedRequest(string $publicId): RideRequest
    {
        $user = request()->user();

        $req = RideRequest::where('public_id', $publicId)->first();

        if (! $req) {
            abort(404, 'Talep bulunamadı.');
        }

        // user.phone ile request.customer_phone normalize karşılaştırması
        $reqPhone  = $this->trustService->normalizePhone((string) $req->customer_phone);
        $userPhone = $this->trustService->normalizePhone((string) ($user->phone ?? ''));

        if ($reqPhone !== $userPhone) {
            abort(404, 'Talep bulunamadı.');
        }

        return $req;
    }

    private function statusPayload(RideRequest $req): array
    {
        // Sayaç: pending / pool_expanded / awaiting_customer_reconfirm üçü de timer kullanır
        $secondsRemaining = 0;
        if (in_array($req->status, ['pending', 'pool_expanded', 'awaiting_customer_reconfirm'], true) && $req->offer_expires_at) {
            $secondsRemaining = max(0, (int) round(now()->diffInSeconds($req->offer_expires_at, false)));
        }

        $payload = [
            'status'                => $req->status,
            'rejection_count'       => (int) $req->rejection_count,
            'current_index'         => (int) $req->current_candidate_index,
            'total_candidates'      => count($req->candidate_driver_ids ?? []),
            'seconds_remaining'     => $secondsRemaining,
            // Auto / havuz dağıtımı (Hadi Gidelim)
            'is_favorite_wave'      => (bool) $req->is_favorite_wave,
            'pool_expanded_at'      => $req->pool_expanded_at?->toIso8601String(),
            'reconfirm_required_at' => $req->reconfirm_required_at?->toIso8601String(),
            'customer_reconfirmed_at' => $req->customer_reconfirmed_at?->toIso8601String(),
            'offered_driver'        => null,
            'accepted_driver'       => null,
            'ride_public_id'        => $req->ride?->public_id,
            // Buluşma noktası — sürücü→pickup ETA + haritada rota için
            'pickup_lat'            => $req->pickup_lat !== null ? (float) $req->pickup_lat : null,
            'pickup_lng'            => $req->pickup_lng !== null ? (float) $req->pickup_lng : null,
            'pickup_address'        => $req->pickup_address,
            'arrived_at'            => $req->driver_arrived_at?->toIso8601String(),
            'customer_confirmed_at' => $req->customer_confirmed_at?->toIso8601String(),
            'no_show_at'            => $req->no_show_at?->toIso8601String(),
            // Eşleşme kodu — yalnızca sürücü VARDIKTAN sonra ve yolculuk
            // başlamadan önce yolcuya gösterilir.
            'match_code'            => ($req->status === 'accepted'
                    && $req->driver_arrived_at !== null
                    && $req->started_at === null)
                ? $req->match_code
                : null,
            'started_at'            => $req->started_at?->toIso8601String(),
            // Görsel doğrulama (araç/sürücü doğru mu?) — yolculuk başladıktan sonra
            'visual_verified_at'      => $req->visual_verified_at?->toIso8601String(),
            'visual_verify_failed_at' => $req->visual_verify_failed_at?->toIso8601String(),
            // Fiyat pazarlığı bloğu (inDrive tarzı)
            'negotiation'           => $this->negotiationPayload($req),
        ];

        if ($req->offered_driver_id) {
            $req->loadMissing(['offeredDriver.user:id,name,avatar', 'offeredDriver.currentVehicle.vehicleClass']);
            $payload['offered_driver'] = $this->driverShortPayload($req->offeredDriver);
        }
        if ($req->accepted_driver_id) {
            $req->loadMissing(['acceptedDriver.user:id,name,avatar,phone', 'acceptedDriver.currentVehicle.vehicleClass']);
            $dp = $this->driverShortPayload($req->acceptedDriver);
            if ($dp) {
                // Anlaşma sağlandı → sürücüyü aramak için telefon aç (gizlilik: yalnızca burada)
                $dp['phone'] = $req->acceptedDriver?->user?->phone;
            }
            $payload['accepted_driver'] = $dp;
        }

        return $payload;
    }

    private function driverShortPayload(?Driver $d): ?array
    {
        if (! $d) return null;

        $fullName  = $d->user?->name ?? 'Sürücü';
        $parts     = preg_split('/\s+/', trim($fullName));
        $shortName = count($parts) > 1
            ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $fullName;

        $v      = $d->currentVehicle;
        $vClass = $v?->vehicleClass;

        $avatarUrl = null;
        if ($d->user?->avatar) {
            $avatarUrl = str_starts_with($d->user->avatar, 'http')
                ? $d->user->avatar
                : asset('storage/' . ltrim($d->user->avatar, '/'));
        }

        return [
            'id'                 => $d->id,
            'name'               => $shortName,
            'full_name'          => $fullName,
            'avatar'             => $avatarUrl,
            'rating'             => (float) $d->rating,
            'trips'              => (int) $d->total_rides,
            'favorite_count'     => (int) ($d->favorite_count ?? 0),
            'is_female'          => $d->user?->gender === 'female',
            'women_only'         => (bool) $d->women_passengers_only,
            'vehicle_class'      => $vClass?->name,
            'vehicle_class_slug' => $vClass?->slug,
            'vehicle_label'      => $v ? trim(($v->brand ?? '') . ' ' . ($v->model ?? '')) : null,
            'vehicle_year'       => $v?->year_of_manufacture,
            'vehicle_color'      => $v?->color,
            'max_passengers'     => (int) ($vClass?->max_passengers ?? 4),
            'plate'              => $v?->plate,
            // Görsel doğrulama için gerçek araç fotoğrafları (yolculuk başlayınca kullanılır)
            'vehicle_photos'     => $this->vehiclePhotoUrls($v),
            // NOT: 'phone' bilerek burada YOK — gizlilik. Yalnızca accepted_driver'a eklenir.
            // Mobil harita marker'ı için sürücünün canlı GPS konumu
            'current_lat'        => $d->current_lat !== null ? (float) $d->current_lat : null,
            'current_lng'        => $d->current_lng !== null ? (float) $d->current_lng : null,
        ];
    }

    /** Araç fotoğraflarını tam URL listesine çevirir (public disk). */
    private function vehiclePhotoUrls($vehicle): array
    {
        if (! $vehicle || ! is_array($vehicle->photos)) return [];
        $photos = array_values(array_filter($vehicle->photos));
        return array_map(
            fn ($p) => str_starts_with($p, 'http') ? $p : asset('storage/' . ltrim($p, '/')),
            $photos,
        );
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earthKm * asin(min(1.0, sqrt($a)));
    }
}
