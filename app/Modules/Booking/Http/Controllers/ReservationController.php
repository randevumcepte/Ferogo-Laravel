<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Services\ReservationService;
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
     * AJAX endpoint: yer arama proxy'si.
     * - Nominatim'i sunucudan çağırır (browser CORS/yavaşlık yok)
     * - İzmir viewbox + bounded=1 ile sonuçları bölgeye odaklar (5-10x hız)
     * - Aynı sorguyu 60 dk cache'ler — tekrarda 0 ms
     * - Kısa timeout (3 sn) — kullanıcı askıda kalmaz
     */
    public function searchPlaces(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $q = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $validated['q'])));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $cacheKey = 'places:tr-izmir:v1:' . sha1($q);

        $results = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($q) {
            try {
                // İzmir geniş viewbox: lon_min, lat_max, lon_max, lat_min
                $response = Http::withHeaders([
                    'User-Agent' => 'Ferogo/1.0 (+https://ferogo.com.tr)',
                    'Accept-Language' => 'tr,en',
                ])->timeout(3)->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q,
                    'format' => 'json',
                    'addressdetails' => 0,
                    'limit' => 6,
                    'countrycodes' => 'tr',
                    'viewbox' => '26.7,38.6,27.5,38.2',
                    'bounded' => 0, // viewbox tercihli ama dışına da bakılsın
                    'accept-language' => 'tr',
                ]);

                if (! $response->ok()) {
                    return [];
                }

                $rows = $response->json();
                if (! is_array($rows)) return [];

                // Sadece UI'nın ihtiyacı olan alanları döndür (payload küçük)
                return array_map(static fn ($r) => [
                    'lat' => (float) ($r['lat'] ?? 0),
                    'lon' => (float) ($r['lon'] ?? 0),
                    'display_name' => (string) ($r['display_name'] ?? ''),
                ], array_slice($rows, 0, 6));
            } catch (\Throwable $e) {
                report($e);
                return [];
            }
        });

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
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
