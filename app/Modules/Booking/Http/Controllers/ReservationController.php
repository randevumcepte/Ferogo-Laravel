<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Services\ReservationService;
use App\Modules\Driver\Models\Driver;
use App\Modules\Pricing\Models\Extra;
use App\Modules\Pricing\Services\FareCalculator;
use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\VehicleClass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $service,
        private FareCalculator $calculator,
    ) {}

    public function index()
    {
        return view('reservation.index', [
            'cities' => City::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'vehicleClasses' => VehicleClass::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'extras' => Extra::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'googleMapsKey' => config('services.google_maps_key'),
        ]);
    }

    public function store(Request $request)
    {
        $cityIds = City::where('is_active', true)->pluck('id')->toArray();
        $vehicleClassIds = VehicleClass::where('is_active', true)->pluck('id')->toArray();
        $extraIds = Extra::where('is_active', true)->pluck('id')->toArray();

        $validated = $request->validate([
            'city_id' => ['required', Rule::in($cityIds)],
            'vehicle_class_id' => ['required', Rule::in($vehicleClassIds)],

            'pickup_address' => ['required', 'string', 'max:255'],
            'pickup_lat' => ['nullable', 'numeric'],
            'pickup_lng' => ['nullable', 'numeric'],
            'pickup_notes' => ['nullable', 'string', 'max:500'],

            'dropoff_address' => ['required', 'string', 'max:255'],
            'dropoff_lat' => ['nullable', 'numeric'],
            'dropoff_lng' => ['nullable', 'numeric'],
            'dropoff_notes' => ['nullable', 'string', 'max:500'],

            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],

            'scheduled_at' => ['required', 'date', 'after:now'],

            'passenger_count' => ['required', 'integer', 'min:1', 'max:8'],
            'luggage_count' => ['nullable', 'integer', 'min:0', 'max:10'],

            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_tc_no' => ['nullable', 'string', 'size:11'],

            'extras' => ['nullable', 'array'],
            'extras.*' => ['nullable', 'array'],
            'extras.*.extra_id' => ['required_with:extras.*', Rule::in($extraIds)],
            'extras.*.quantity' => ['required_with:extras.*', 'integer', 'min:1', 'max:10'],

            'kvkk_consent' => ['required', 'accepted'],
        ], [
            'kvkk_consent.accepted' => 'KVKK onayını işaretlemeniz gerekiyor.',
            'scheduled_at.after' => 'Tarih geçmiş bir zaman olamaz.',
            'customer_tc_no.size' => 'T.C. Kimlik numarası 11 haneli olmalıdır.',
        ]);

        $ride = $this->service->create($validated);

        return redirect()
            ->route('reservation.confirmation', $ride->public_id)
            ->with('success', 'Rezervasyonunuz oluşturuldu! En kısa sürede sizi arayacağız.');
    }

    public function confirmation(string $publicId)
    {
        $ride = Ride::with(['city', 'vehicleClass', 'extras.extra'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        return view('reservation.confirmation', compact('ride'));
    }

    /**
     * AJAX endpoint: Canlı radarda seçilen şoföre hızlı talep.
     * Anlık konum + bırakış noktasıyla rezervasyon oluşturur,
     * tercih edilen şoför adını pickup_notes'e yazar.
     */
    public function quickRequest(Request $request): JsonResponse
    {
        $vehicleClassSlugs = VehicleClass::where('is_active', true)->pluck('slug')->toArray();

        $validated = $request->validate([
            'vehicle_class_slug' => ['required', Rule::in($vehicleClassSlugs)],
            'pickup_address' => ['required', 'string', 'max:255'],
            'pickup_lat' => ['required', 'numeric'],
            'pickup_lng' => ['required', 'numeric'],
            'dropoff_address' => ['required', 'string', 'max:255'],
            'dropoff_lat' => ['nullable', 'numeric'],
            'dropoff_lng' => ['nullable', 'numeric'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'preferred_driver_name' => ['required', 'string', 'max:120'],
            'preferred_driver_plate' => ['nullable', 'string', 'max:32'],
            'distance_km' => ['required', 'numeric', 'min:0', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'kvkk_consent' => ['required', 'accepted'],
        ], [
            'kvkk_consent.accepted' => 'KVKK onayını işaretlemen gerekiyor.',
        ]);

        $vehicleClass = VehicleClass::where('slug', $validated['vehicle_class_slug'])->firstOrFail();

        // Varsayılan şehir: İzmir (radar sadece İzmir kapsamında çalışıyor)
        $city = City::where('is_active', true)
            ->where(function ($q) {
                $q->where('slug', 'izmir')->orWhere('name', 'like', '%zmir%');
            })
            ->orderBy('sort_order')
            ->first()
            ?? City::where('is_active', true)->orderBy('sort_order')->firstOrFail();

        $ride = $this->service->create([
            'city_id' => $city->id,
            'vehicle_class_id' => $vehicleClass->id,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'pickup_address' => $validated['pickup_address'],
            'pickup_lat' => $validated['pickup_lat'],
            'pickup_lng' => $validated['pickup_lng'],
            'pickup_notes' => 'Radar · Tercih edilen sürücü: ' . $validated['preferred_driver_name']
                . (! empty($validated['preferred_driver_plate']) ? ' (' . $validated['preferred_driver_plate'] . ')' : ''),
            'dropoff_address' => $validated['dropoff_address'],
            'dropoff_lat' => $validated['dropoff_lat'] ?? null,
            'dropoff_lng' => $validated['dropoff_lng'] ?? null,
            'distance_km' => $validated['distance_km'],
            'duration_minutes' => $validated['duration_minutes'],
            'passenger_count' => 1,
            'luggage_count' => 0,
            'scheduled_at' => now()->addMinutes(5)->toIso8601String(),
            'source' => 'radar_quick',
        ]);

        return response()->json([
            'success' => true,
            'public_id' => $ride->public_id,
            'total_fare' => (float) $ride->total_fare,
            'currency' => $ride->currency,
            'driver_name' => $validated['preferred_driver_name'],
            'message' => 'Talebin ' . $validated['preferred_driver_name'] . '\'e iletildi.',
        ]);
    }

    /**
     * AJAX endpoint: kullanıcının konumuna en yakın N müsait sürücüyü döner.
     * Bounding-box ön filtre + haversine sıralama (SQLite uyumlu).
     */
    public function nearbyDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lng'    => ['required', 'numeric', 'between:-180,180'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:10'],
            'radius' => ['nullable', 'numeric', 'min:0.5', 'max:50'], // km
        ]);

        $lat    = (float) $validated['lat'];
        $lng    = (float) $validated['lng'];
        $limit  = (int) ($validated['limit'] ?? 3);
        $radius = (float) ($validated['radius'] ?? 10.0);

        // ~1° lat ≈ 111 km, ~1° lng ≈ 111 * cos(lat) km
        $latDelta = $radius / 111.0;
        $lngDelta = $radius / (111.0 * max(0.000001, cos(deg2rad($lat))));

        $candidates = Driver::query()
            ->with(['user:id,name', 'currentVehicle.vehicleClass'])
            ->where('approval_status', 'approved')
            ->whereIn('availability_status', ['online', 'busy'])
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->whereBetween('current_lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('current_lng', [$lng - $lngDelta, $lng + $lngDelta])
            ->limit(50)
            ->get();

        $scored = $candidates->map(function (Driver $d) use ($lat, $lng) {
            $km = $this->haversineKm($lat, $lng, (float) $d->current_lat, (float) $d->current_lng);

            // İsim gizliliği: "Mehmet Karaca" → "Mehmet K."
            $fullName = $d->user?->name ?? 'Sürücü';
            $parts = preg_split('/\s+/', trim($fullName));
            $shortName = count($parts) > 1
                ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
                : $fullName;

            $vehicle = $d->currentVehicle;
            $vClass  = $vehicle?->vehicleClass;

            return [
                'id'                  => $d->id,
                'name'                => $shortName,
                'rating'              => (float) $d->rating,
                'trips'               => (int) $d->total_rides,
                'vehicle_class'       => $vClass?->name ?? 'Easy',
                'vehicle_class_slug'  => $vClass?->slug ?? 'easy',
                'vehicle_label'       => $vehicle ? trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) : null,
                'plate'               => $vehicle?->plate,
                'lat'                 => (float) $d->current_lat,
                'lng'                 => (float) $d->current_lng,
                'distance_km'         => round($km, 2),
                'eta_minutes'         => max(1, (int) round($km * 2.4 + 0.8)), // ~25 km/saat şehir içi
                'is_available'        => $d->availability_status === 'online',
            ];
        })->values();

        $available = $scored->where('is_available', true)
            ->sortBy('distance_km')
            ->take($limit)
            ->values();

        return response()->json([
            'success'         => true,
            'drivers'         => $available,
            'available_count' => $scored->where('is_available', true)->count(),
            'total_count'     => $scored->count(),
            'radius_km'       => $radius,
        ]);
    }

    /**
     * AJAX endpoint: Yer arama proxy'si.
     * Nominatim'i sunucu tarafında çağırır, sonuçları 60 dk cache'ler.
     * Browser'dan direkt çağrıya göre çok daha hızlı (cache hit + İzmir viewbox).
     */
    public function searchPlaces(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'   => ['required', 'string', 'min:2', 'max:120'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $q = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $validated['q'])));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        // İzmir viewbox (geniş) — sonuçları bölgeye yaklaştırır, hız artar
        // bbox: lon_min, lat_max, lon_max, lat_min
        $viewbox = '26.5,38.85,27.85,38.05';
        $cacheKey = 'places:' . md5($q . '|' . $viewbox);

        $results = Cache::remember($cacheKey, now()->addHours(6), function () use ($q, $viewbox) {
            try {
                $response = Http::withHeaders([
                        'User-Agent' => 'Ferogo/1.0 (+https://ferogo.com.tr)',
                        'Accept-Language' => 'tr,en',
                    ])
                    ->timeout(4)
                    ->connectTimeout(2)
                    ->retry(1, 200)
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $q,
                        'format' => 'json',
                        'limit' => 6,
                        'countrycodes' => 'tr',
                        'viewbox' => $viewbox,
                        'bounded' => 0, // viewbox sadece bias, dışına da bakar
                        'addressdetails' => 0,
                        'dedupe' => 1,
                    ]);

                if (! $response->ok()) {
                    return [];
                }

                return collect($response->json())
                    ->map(function ($item) {
                        $name = $item['display_name'] ?? '';
                        $parts = array_map('trim', explode(',', $name));
                        return [
                            'lat'         => (float) ($item['lat'] ?? 0),
                            'lng'         => (float) ($item['lon'] ?? 0),
                            'display'     => $name,
                            'primary'     => implode(', ', array_slice($parts, 0, 2)),
                            'secondary'   => implode(', ', array_slice($parts, 2)),
                            'type'        => $item['type'] ?? null,
                            'importance'  => (float) ($item['importance'] ?? 0),
                        ];
                    })
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        });

        // Eğer kullanıcı konumu verdiyse, sonuçları ona göre yeniden sırala
        if (isset($validated['lat'], $validated['lng']) && ! empty($results)) {
            $userLat = (float) $validated['lat'];
            $userLng = (float) $validated['lng'];
            usort($results, function ($a, $b) use ($userLat, $userLng) {
                $da = $this->haversineKm($userLat, $userLng, $a['lat'], $a['lng']);
                $db = $this->haversineKm($userLat, $userLng, $b['lat'], $b['lng']);
                return $da <=> $db;
            });
        }

        return response()->json([
            'success' => true,
            'results' => $results,
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
     * AJAX endpoint: form değiştikçe canlı fiyat hesabı.
     */
    public function calculateFare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'vehicle_class_id' => ['required', 'integer', 'exists:vehicle_classes,id'],
            'distance_km' => ['required', 'numeric', 'min:0', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'scheduled_at' => ['nullable', 'date'],
            'extras' => ['nullable', 'array'],
            'extras.*.extra_id' => ['integer', 'exists:extras,id'],
            'extras.*.quantity' => ['integer', 'min:1', 'max:10'],
        ]);

        $scheduledAt = ! empty($validated['scheduled_at'])
            ? Carbon::parse($validated['scheduled_at'])
            : null;

        $fare = $this->calculator->calculate(
            cityId: (int) $validated['city_id'],
            vehicleClassId: (int) $validated['vehicle_class_id'],
            distanceKm: (float) $validated['distance_km'],
            durationMinutes: (int) $validated['duration_minutes'],
            extras: $validated['extras'] ?? [],
            scheduledAt: $scheduledAt,
        );

        return response()->json([
            'success' => true,
            'fare' => $fare,
        ]);
    }
}
