<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\DispatcherService;
use App\Modules\Booking\Services\FavoriteDriverService;
use App\Modules\Booking\Services\NoShowService;
use App\Modules\Booking\Services\PhoneVerificationService;
use App\Modules\Booking\Services\RideRequestService;
use App\Modules\Booking\Support\NegotiationPayload;
use App\Modules\Driver\Models\Driver;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class RideRequestController extends Controller
{
    use NegotiationPayload;

    public function __construct(
        private RideRequestService $service,
        private CustomerTrustService $trustService,
        private PhoneVerificationService $verificationService,
        private NoShowService $noShowService,
        private DispatcherService $dispatcher,
        private FavoriteDriverService $favoriteService,
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
        // PAKET KONTROL: aktif paketi olmayan sürücü radar'a düşmez.
        $candidates = Driver::query()
            ->with(['user:id,name,avatar,gender', 'currentVehicle.vehicleClass'])
            ->withCount('favoritedByUsers as favorite_count')
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->when(config('services.driver.enforce_packages', true), fn ($q) => $q
                ->whereNotNull('package_active_until')
                ->where('package_active_until', '>', now()))
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
                // Radar haritasında gerçek konumdan marker çizmek için canlı GPS
                'current_lat' => $d->current_lat !== null ? (float) $d->current_lat : null,
                'current_lng' => $d->current_lng !== null ? (float) $d->current_lng : null,
            ]);
        })->sortBy('distance_km')->take($limit)->values();

        // Diagnostic counts — frontend hatalı mesaj göstermek için kullanabilir
        $totalOnline = Driver::query()
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->when(config('services.driver.enforce_packages', true), fn ($q) => $q
                ->whereNotNull('package_active_until')
                ->where('package_active_until', '>', now()))
            ->count();

        return response()->json([
            'success'       => true,
            'drivers'       => $scored,
            'total_online'  => $totalOnline,
        ]);
    }

    /**
     * GET /api/drivers/{driverId}/profile — sürücüyü "Seç"e basmadan önce inceleme.
     * Avatar, biyografi, deneyim, sertifikalar, araç fotoğrafları ve özellikleri.
     */
    public function driverProfile(int $driverId): JsonResponse
    {
        /** @var Driver|null $driver */
        $driver = Driver::query()
            ->with(['user', 'currentVehicle.vehicleClass'])
            ->withCount('favoritedByUsers as favorite_count')
            ->where('approval_status', 'approved')
            ->find($driverId);

        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Sürücü bulunamadı.'], 404);
        }

        $user    = $driver->user;
        $vehicle = $driver->currentVehicle;
        $vClass  = $vehicle?->vehicleClass;

        // ─── Deneyim ───
        $yearsDriving = null;
        if ($driver->license_issued_at) {
            $yearsDriving = (int) $driver->license_issued_at->diffInYears(now());
        }
        $expBandLabels = [
            'under_1' => '1 yıldan az',
            '1_to_3'  => '1-3 yıl',
            '3_to_5'  => '3-5 yıl',
            '5_plus'  => '5+ yıl',
        ];
        $experience = [
            'band'          => $driver->experience_band,
            'label'         => $expBandLabels[$driver->experience_band] ?? '—',
            'years_driving' => $yearsDriving,
        ];

        // ─── Sertifikalar / Belgeler ───
        $credentials = [
            [
                'key'    => 'approved',
                'label'  => 'Onaylı Sürücü',
                'icon'   => '✓',
                'valid'  => $driver->approval_status === 'approved',
                'detail' => 'FerXGo tarafından doğrulanmış.',
            ],
            [
                'key'    => 'license',
                'label'  => 'Ehliyet',
                'icon'   => '🪪',
                'valid'  => $driver->license_expires_at === null || $driver->license_expires_at->isFuture(),
                'detail' => $driver->license_class ? 'Sınıf ' . $driver->license_class : null,
            ],
            [
                'key'    => 'src',
                'label'  => 'SRC Sertifikası',
                'icon'   => '📜',
                'valid'  => $driver->src_certificate_number && ($driver->src_expires_at === null || $driver->src_expires_at->isFuture()),
                'detail' => $driver->src_certificate_number ? 'Geçerli' : 'Henüz alınmamış',
            ],
            [
                'key'    => 'psychotechnic',
                'label'  => 'Psikoteknik',
                'icon'   => '🧠',
                'valid'  => $driver->psychotechnic_test_at && $driver->psychotechnic_test_at->isAfter(now()->subYears(5)),
                'detail' => $driver->psychotechnic_test_at ? 'Onaylı' : null,
            ],
            [
                'key'    => 'criminal_record',
                'label'  => 'Adli Sicil',
                'icon'   => '🛡',
                'valid'  => $driver->criminal_record_at && $driver->criminal_record_at->isAfter(now()->subYears(2)),
                'detail' => $driver->criminal_record_at ? 'Temiz' : null,
            ],
        ];

        // ─── Otomatik biyografi (mevcut alanlardan dinamik üretim) ───
        $bioParts = [];
        if ($experience['years_driving']) {
            $bioParts[] = $experience['years_driving'] . ' yıllık deneyimli üye sürücü';
        } elseif ($experience['label'] !== '—') {
            $bioParts[] = $experience['label'] . ' deneyim';
        }
        if ($driver->total_rides > 0) {
            $bioParts[] = number_format((int) $driver->total_rides, 0, ',', '.') . ' tamamlanmış yolculuk';
        }
        $verifiedCount = count(array_filter($credentials, fn ($c) => $c['valid']));
        if ($verifiedCount >= 4) {
            $bioParts[] = $verifiedCount . ' resmi belgesi doğrulanmış';
        }
        $bio = empty($bioParts)
            ? 'Yeni katılan profesyonel sürücü.'
            : implode(' · ', $bioParts) . '.';

        // ─── Araç (PRIVACY: plaka + gerçek fotoğraflar gizli — Martı dispatcher modeli) ───
        // Müşteri henüz sürücüyü seçmedi → trafik/sürücü gizliliği için kritik bilgiler kapalı
        $vehiclePayload = null;
        if ($vehicle) {
            $features = [];
            if ($vehicle->has_baby_seat)    $features[] = ['key' => 'baby_seat',    'label' => 'Bebek koltuğu', 'icon' => '👶'];
            if ($vehicle->has_child_seat)   $features[] = ['key' => 'child_seat',   'label' => 'Çocuk koltuğu', 'icon' => '🧒'];
            if ($vehicle->has_booster_seat) $features[] = ['key' => 'booster_seat', 'label' => 'Yükseltici',   'icon' => '🪑'];
            if ($vehicle->pet_friendly)     $features[] = ['key' => 'pet_friendly', 'label' => 'Evcil hayvan dostu', 'icon' => '🐾'];

            $vehiclePayload = [
                'brand'             => $vehicle->brand,
                'model'             => $vehicle->model,
                'year'              => $vehicle->year_of_manufacture,
                'color'             => $vehicle->color,
                'class_name'        => $vClass?->name,
                'class_slug'        => $vClass?->slug,
                // Temsili sınıf görseli (gerçek araç fotoğrafı değil)
                'class_icon_url'    => $this->vehiclePhotoUrl($vehicle, $vClass),
                'features'          => $features,
                'insurance_valid'   => $vehicle->insurance_expires_at === null || $vehicle->insurance_expires_at->isFuture(),
                'inspection_valid'  => $vehicle->inspection_expires_at === null || $vehicle->inspection_expires_at->isFuture(),
                // Privacy bayrakları
                'plate_hidden'      => true,
                'photos_hidden'     => true,
                'privacy_note'      => 'Plaka ve araç fotoğrafları eşleştirme sonrası açılacaktır.',
            ];
        }

        $fullName = $user?->name ?? 'Sürücü';
        $parts    = preg_split('/\s+/', trim($fullName));
        $shortName = count($parts) > 1
            ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $fullName;

        // PRIVACY: gerçek avatar gizli — temsili UI avatar (kişisel kimlik gizliliği)
        $avatarUrl = $this->avatarInitialsUrl($fullName);

        return response()->json([
            'success' => true,
            'driver'  => [
                'id'             => $driver->id,
                'name'           => $shortName,           // sadece kısa isim (ad + soyad baş harfi)
                'short_name'     => $shortName,
                'avatar'         => $avatarUrl,           // temsili görsel
                'rating'         => (float) $driver->rating,
                'total_rides'    => (int) $driver->total_rides,
                'member_since'   => $user?->created_at?->format('Y-m'),
                'experience'     => $experience,
                'credentials'    => $credentials,
                'bio'            => $bio,
                'vehicle'        => $vehiclePayload,
                'is_favorite'    => $this->favoriteService->isFavorite(Auth::user(), $driver->id),
                'favorite_count' => (int) ($driver->favorite_count ?? 0),
                'is_female'      => $user?->gender === 'female',
                'women_only'     => (bool) $driver->women_passengers_only,
                'privacy_level'  => 'public',
                'privacy_note'   => 'Eşleştirme sonrası tam profil bilgilerine erişim açılacaktır.',
            ],
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
        // Debug log — her ride_request POST'unu kayıt al
        $logKey = '[RR-STORE ' . substr(uniqid(), -6) . ']';
        $customerAuthed = Auth::guard('customer')->user();
        \Illuminate\Support\Facades\Log::info($logKey . ' input', [
            'auth_id'           => $customerAuthed?->id,
            'auth_type'         => $customerAuthed?->type,
            'auth_phone'        => $customerAuthed?->phone,
            'auth_verified_at'  => $customerAuthed?->phone_verified_at,
            'fields' => collect($request->only([
                'vehicle_class_slug', 'customer_name', 'customer_phone',
                'pickup_address', 'dropoff_address', 'distance_km', 'duration_minutes',
                'preferred_driver_id', 'kvkk_consent',
            ]))->map(fn ($v) => is_string($v) ? mb_substr($v, 0, 60) : $v)->toArray(),
            'has_verification_token' => ! empty($request->input('verification_token')),
            'has_fingerprint'        => ! empty($request->input('fingerprint')),
        ]);

        $vehicleClassSlugs = VehicleClass::where('is_active', true)->pluck('slug')->toArray();

        // MÜŞTERİ guard'ında login varsa OTP token zorunlu değil.
        // Driver guard'a bakmıyoruz — bu endpoint müşteri akışıdır.
        $authed = $customerAuthed;
        $isAuthedCustomer = $authed && $authed->type === 'customer';

        // Authed customer'a phone_verified_at null geldiyse, transparan olarak şimdi işaretle
        if ($isAuthedCustomer && ! $authed->phone_verified_at) {
            $authed->forceFill(['phone_verified_at' => now()])->save();
        }

        try {
            $validated = $request->validate([
                'vehicle_class_slug'    => ['required', Rule::in($vehicleClassSlugs)],
                'pickup_address'        => ['required', 'string', 'max:255'],
                'pickup_lat'            => ['required', 'numeric'],
                'pickup_lng'            => ['required', 'numeric'],
                'dropoff_address'       => ['required', 'string', 'max:255'],
                'dropoff_lat'           => ['nullable', 'numeric'],
                'dropoff_lng'           => ['nullable', 'numeric'],
                'customer_name'         => [$isAuthedCustomer ? 'nullable' : 'required', 'string', 'max:120'],
                'customer_phone'        => [$isAuthedCustomer ? 'nullable' : 'required', 'string', 'max:20'],
                'verification_token'    => [$isAuthedCustomer ? 'nullable' : 'required', 'string', 'size:48'],
                'fingerprint'           => ['nullable', 'string', 'max:64'],
                'distance_km'           => ['required', 'numeric', 'min:0', 'max:500'],
                'duration_minutes'      => ['required', 'integer', 'min:1', 'max:600'],
                'estimated_fare'        => ['nullable', 'numeric', 'min:0'],
                'suggested_fare'        => ['nullable', 'numeric', 'min:0'],
                'customer_offer_fare'   => ['nullable', 'numeric', 'min:0'],
                'preferred_driver_id'   => ['required', 'integer', 'exists:drivers,id'],
                'fallback_driver_ids'   => ['nullable', 'array', 'max:5'],
                'fallback_driver_ids.*' => ['integer', 'exists:drivers,id'],
                'kvkk_consent'          => ['required', 'accepted'],
            ], [
                'kvkk_consent.accepted'       => 'KVKK onayını işaretlemen gerekiyor.',
                'verification_token.required' => 'Telefonunu doğrulaman gerekiyor. SMS kodunu gir.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::warning($logKey . ' validation_failed', [
                'is_authed_customer' => $isAuthedCustomer,
                'errors'             => $e->errors(),
            ]);
            throw $e;
        }

        // Login müşteri: name/phone session'dan
        if ($isAuthedCustomer) {
            $validated['customer_phone'] = $authed->phone;
            $validated['customer_name']  = $validated['customer_name'] ?? $authed->name;
        }

        $ip          = $request->ip();
        $fingerprint = $validated['fingerprint'] ?? null;

        // ─── KORUMA KATMANI ─────────────────────────────────────
        // 1) Trust + ban kontrolü (login olsa da olmasa da)
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

        // 2) OTP token doğrulama — yalnızca login değilse
        if (! $isAuthedCustomer) {
            $tokenCheck = $this->verificationService->validateToken(
                $validated['customer_phone'], $validated['verification_token'],
            );
            if (! $tokenCheck['ok']) {
                return response()->json([
                    'success'                 => false,
                    'message'                 => $tokenCheck['message'],
                    'phone_reverify_required' => true,
                ], 422);
            }
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

        // Sadece online + approved + aktif paketli + müsait sürücüler aday olabilir
        // Kadın sürücü güvenliği: "sadece kadın yolcu al" diyen sürücüler yalnızca
        // kadın müşterilere aday olabilir (misafir/erkek müşteriye gösterilmez).
        $customerIsFemale = $authed && $authed->gender === 'female';

        $validCandidates = Driver::query()
            ->whereIn('id', $candidates)
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->when(config('services.driver.enforce_packages', true), fn ($q) => $q
                ->whereNotNull('package_active_until')
                ->where('package_active_until', '>', now()))
            ->when(! $customerIsFemale, fn ($q) => $q->where('women_passengers_only', false))
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
            'suggested_fare'       => isset($validated['suggested_fare']) ? (float) $validated['suggested_fare'] : (isset($validated['estimated_fare']) ? (float) $validated['estimated_fare'] : null),
            'customer_offer_fare'  => isset($validated['customer_offer_fare']) ? (float) $validated['customer_offer_fare'] : null,
            'candidate_driver_ids' => $orderedCandidates,
            'phone_verified_at'    => now(),
            'verification_token'   => $validated['verification_token'] ?? null,
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
     * POST /api/ride-requests/{publicId}/counter — müşteri sürücünün karşı teklifine yeni fiyat verir.
     * Body: { amount }
     */
    public function counter(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $req    = RideRequest::where('public_id', $publicId)->firstOrFail();
        $result = $this->service->customerCounter($req, (float) $validated['amount']);

        return response()->json(array_merge(['success' => $result['ok']], $result), $result['ok'] ? 200 : 422);
    }

    /**
     * POST /api/ride-requests/{publicId}/accept-price — müşteri sürücünün karşı teklifini kabul eder.
     * Anlaşma → yolculuk başlar.
     */
    public function acceptPrice(string $publicId): JsonResponse
    {
        $req    = RideRequest::where('public_id', $publicId)->firstOrFail();
        $result = $this->service->customerAcceptCounter($req);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'İşlem yapılamadı.'], 422);
        }

        return response()->json([
            'success' => true,
            'status'  => $this->statusPayload($result['request']),
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
     * POST /api/ride-requests/{publicId}/reconfirm
     * Müşteri fallback (havuz) sürücüsünü onaylar veya reddeder.
     * Body: { accept: true|false }
     */
    public function reconfirm(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'accept' => ['required', 'boolean'],
        ]);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        if ($req->status !== 'awaiting_customer_reconfirm') {
            return response()->json([
                'success' => false,
                'message' => 'Bu talep şu an reconfirm bekliyor değil.',
            ], 422);
        }

        try {
            $req = $this->dispatcher->customerReconfirm($req, (bool) $validated['accept']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'İşlem sırasında hata: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'status'  => $req->status,
            'message' => $validated['accept']
                ? 'Yeni sürücü onaylandı, yolculuk başlatılıyor.'
                : 'Yeni sürücü reddedildi, talep iptal edildi.',
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/visual-verify
     * Yolculuk başladıktan sonra müşteri "sürücü/araç fotoğrafı tutuyor mu?" sorusuna cevap verir.
     * Body: { match: true|false, note?: string }
     *
     * - match=true  → visual_verified_at set
     * - match=false → security incident otomatik açılır (Faz 6'da implement)
     */
    public function visualVerify(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'match' => ['required', 'boolean'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        if (! in_array($req->status, ['accepted', 'in_progress'], true) || ! $req->started_at) {
            return response()->json([
                'success' => false,
                'message' => 'Görsel doğrulama yalnızca yolculuk başladıktan sonra yapılabilir.',
            ], 422);
        }

        if ($validated['match']) {
            $req->update(['visual_verified_at' => now()]);
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => '✓ Müşteri sürücü ve araç görsel doğrulamasını onayladı.',
            ]);
            return response()->json([
                'success' => true,
                'verified' => true,
                'message' => 'İyi yolculuklar!',
            ]);
        }

        // Eşleşmiyor → security incident aç (Faz 6 detaylı işleyecek)
        $req->update(['visual_verify_failed_at' => now()]);

        $incident = \App\Modules\Security\Models\SecurityIncident::create([
            'ride_request_id'   => $req->id,
            'ride_id'           => $req->ride_id,
            'driver_id'         => $req->accepted_driver_id,
            'type'              => \App\Modules\Security\Models\SecurityIncident::TYPE_VISUAL_MISMATCH,
            'reported_by'       => 'customer',
            'reporter_note'     => $validated['note'] ?? null,
            'severity'          => 'high',
            'lat'               => $req->pickup_lat,
            'lng'               => $req->pickup_lng,
        ]);

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => '⚠ Müşteri sürücü/araç eşleşmediğini bildirdi. Çağrı merkezi devreye giriyor.',
        ]);

        return response()->json([
            'success' => true,
            'verified' => false,
            'incident_id' => $incident->public_id,
            'message' => 'Çağrı merkezi sürücüyle hemen iletişime geçecek. Güvenliğiniz için araçtan inebilirsiniz.',
        ]);
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
        // Saniye sayacı — pending/pool_expanded/awaiting_customer_reconfirm üçü de timer kullanır
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
            // Faz 3 — dispatcher
            'pool_expanded_at'      => $req->pool_expanded_at?->toIso8601String(),
            'pool_candidate_driver_ids' => $req->pool_candidate_driver_ids ?? [],
            'pool_rejected_driver_ids'  => $req->pool_rejected_driver_ids ?? [],
            'reconfirm_required_at' => $req->reconfirm_required_at?->toIso8601String(),
            'customer_reconfirmed_at' => $req->customer_reconfirmed_at?->toIso8601String(),
            // Faz 5 — boarding & ride start
            'boarding_question_at'  => $req->boarding_question_at?->toIso8601String(),
            'boarding_confirmed_at' => $req->boarding_confirmed_at?->toIso8601String(),
            'started_at'            => $req->started_at?->toIso8601String(),
            // Faz 6 — visual verification
            'visual_verify_prompted_at' => $req->visual_verify_prompted_at?->toIso8601String(),
            'visual_verified_at'    => $req->visual_verified_at?->toIso8601String(),
            'visual_verify_failed_at' => $req->visual_verify_failed_at?->toIso8601String(),
            'completed_at'          => $req->completed_at?->toIso8601String(),
            'offered_driver'        => null,
            'accepted_driver'       => null,
            'ride_public_id'        => $req->ride?->public_id,
            'arrived_at'            => $req->driver_arrived_at?->toIso8601String(),
            'customer_confirmed_at' => $req->customer_confirmed_at?->toIso8601String(),
            'no_show_at'            => $req->no_show_at?->toIso8601String(),
            // Fiyat pazarlığı bloğu (inDrive tarzı)
            'negotiation'           => $this->negotiationPayload($req),
        ];

        // Privacy seviyesi: müşteri sürücüyü onayladıktan SONRA tam bilgi açılır
        // (customer_confirmed_at, accepted, in_progress, driver_arrived vb.)
        // Henüz onaylamadıysa (pending/offered/awaiting_reconfirm) gizli kalır.
        $matchedPrivacy = in_array($req->status, ['accepted', 'driver_arriving', 'driver_arrived', 'in_progress', 'completed'], true)
            || $req->customer_confirmed_at !== null
            || $req->customer_reconfirmed_at !== null;
        $privacy = $matchedPrivacy ? 'matched' : 'public';

        if ($req->offered_driver_id) {
            $req->loadMissing(['offeredDriver.user:id,name,avatar', 'offeredDriver.currentVehicle.vehicleClass']);
            $payload['offered_driver'] = $this->driverPayload($req->offeredDriver, $privacy);
        }
        if ($req->accepted_driver_id) {
            $req->loadMissing(['acceptedDriver.user:id,name,avatar', 'acceptedDriver.currentVehicle.vehicleClass']);
            $payload['accepted_driver'] = $this->driverPayload($req->acceptedDriver, $privacy);
        }

        return $payload;
    }

    /**
     * Sürücü bilgilerini API'ye uygun formatta döner.
     *
     * Gizlilik seviyeleri (Martı TAG dispatcher modeli):
     *   - 'public'  : Plaka, gerçek araç fotoğrafı ve sürücü gerçek fotoğrafı GİZLİ.
     *                 Müşteri henüz eşleştirme talep etmemiş (haritada yakın araçlar).
     *                 Trafik takibine karşı koruma + sürücü gizliliği.
     *   - 'matched' : Plaka, araç fotoğrafları ve sürücü fotoğrafı AÇIK.
     *                 Sadece müşteri sürücüyle eşleştirildikten sonra (ride accepted).
     *                 Sürücü tarafına da kendi bilgileri tam dönen tek seviye.
     */
    private function driverPayload(?Driver $d, string $privacy = 'public'): ?array
    {
        if (! $d) return null;

        $fullName = $d->user?->name ?? 'Sürücü';
        $parts = preg_split('/\s+/', trim($fullName));
        $shortName = count($parts) > 1
            ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $fullName;

        $v = $d->currentVehicle;
        $vClass = $v?->vehicleClass;

        // Public payload (eşleştirme öncesi): plaka YOK, gerçek araç fotoğrafı YOK
        $payload = [
            'id'                  => $d->id,
            'name'                => $shortName,
            'rating'              => (float) $d->rating,
            'trips'               => (int) $d->total_rides,
            'vehicle_class'       => $vClass?->name,
            'vehicle_class_slug'  => $vClass?->slug,
            'vehicle_label'       => $v ? trim(($v->brand ?? '') . ' ' . ($v->model ?? '')) : null,
            'vehicle_year'        => $v?->year_of_manufacture,
            'vehicle_color'       => $v?->color,
            'experience_band'     => $d->experience_band,
            // Sosyal kanıt: kaç müşteri bu sürücüyü favoriledi (radar "♥ N" rozeti)
            'favorite_count'      => (int) ($d->favorite_count ?? 0),
            // Kadın sürücü güvenliği
            'is_female'           => $d->user?->gender === 'female',
            'women_only'          => (bool) $d->women_passengers_only,
            // Genel temsili görsel (araç sınıfı bazlı, gerçek araç değil)
            'vehicle_class_icon'  => $this->vehiclePhotoUrl($v, $vClass),
            // Sürücü için temsili UI avatar (kişisel fotoğraf değil)
            'avatar_initials_url' => $this->avatarInitialsUrl($fullName),
            // Privacy bayrakları (frontend için)
            'privacy_level'       => 'public',
            'plate_hidden'        => true,
            'photos_hidden'       => true,
        ];

        if ($privacy === 'matched') {
            // Eşleştirme sonrası: tam bilgi aç
            $photos = $this->vehiclePhotos($v, $vClass);
            $payload = array_merge($payload, [
                'full_name'           => $fullName,
                'photo_url'           => $this->driverPhotoUrl($d),
                'vehicle_photo_url'   => $photos[0] ?? $this->vehiclePhotoUrl($v, $vClass),
                'vehicle_photos'      => $photos,
                'plate'               => $v?->plate,
                'privacy_level'       => 'matched',
                'plate_hidden'        => false,
                'photos_hidden'       => false,
            ]);
        }

        return $payload;
    }

    /**
     * İsim baş harflerinden temsili avatar (kişisel fotoğraf değil — gizlilik için).
     */
    private function avatarInitialsUrl(string $name): string
    {
        $encoded = urlencode($name);
        return "https://ui-avatars.com/api/?name={$encoded}&background=F0C040&color=000&size=256&bold=true&format=svg";
    }

    /** Aracın gerçek fotoğraf galerisi varsa onu döner; yoksa sınıf bazlı temsili tek görsel array'i. */
    private function vehiclePhotos($v, $vClass): array
    {
        if ($v && is_array($v->photos) && count($v->photos) > 0) {
            return array_map(
                fn ($p) => str_starts_with($p, 'http') ? $p : asset('storage/' . $p),
                $v->photos
            );
        }
        return [$this->vehiclePhotoUrl($v, $vClass)];
    }

    /** Gerçek avatar varsa onu döner, yoksa initial-based UI Avatars URL'i üretir. */
    private function driverPhotoUrl(Driver $d): string
    {
        $avatar = $d->user?->avatar;
        if ($avatar) {
            // Tam URL (örn. pravatar) doğrudan döner; relative yol storage'a yönlenir
            return str_starts_with($avatar, 'http') ? $avatar : asset('storage/' . $avatar);
        }
        $name = urlencode($d->user?->name ?? 'Sürücü');
        return "https://ui-avatars.com/api/?name={$name}&background=F0C040&color=000&size=256&bold=true&format=svg";
    }

    /** Araç fotoğrafı — sınıfa göre temsili görsel. */
    private function vehiclePhotoUrl($v, $vClass): string
    {
        $slug = $vClass?->slug ?? 'easy';
        // Sınıfa göre Unsplash temsili görsel
        $map = [
            'easy'     => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=600&q=70&auto=format',  // sedan
            'platinum' => 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=600&q=70&auto=format',    // luxury sedan
            'vip'      => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&q=70&auto=format', // s-class
        ];
        return $map[$slug] ?? $map['easy'];
    }
}
