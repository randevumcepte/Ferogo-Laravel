<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\DispatcherService;
use App\Modules\Booking\Services\NoShowService;
use App\Modules\Booking\Services\RideRequestService;
use App\Modules\Booking\Support\NegotiationPayload;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class DriverPanelController extends Controller
{
    use NegotiationPayload;

    public function __construct(
        private RideRequestService $service,
        private NoShowService $noShowService,
        private CustomerTrustService $trustService,
    ) {}

    // ────────────────────────────────────────────────────────────
    // AUTH
    // ────────────────────────────────────────────────────────────

    public function showLogin(): View|RedirectResponse
    {
        if ($this->currentDriver()) {
            return redirect()->route('driver.panel');
        }
        return view('driver.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])
            ->where('type', 'driver')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'E-posta veya şifre hatalı.']);
        }

        if ($user->status !== 'active') {
            return back()->withErrors(['email' => 'Hesabın aktif değil.']);
        }

        // SÜRÜCÜ guard — müşteri oturumundan tamamen bağımsız (aynı tarayıcıda
        // hem yolcu hem üye sürücü olarak login kalmak mümkün).
        Auth::guard('driver')->login($user, remember: true);

        // ÖNEMLİ: session()->regenerate() KULLANMA — paralel müşteri oturumunu
        // (login_customer_xxx) yok eder. Sadece CSRF token'ı yenile.
        $request->session()->regenerateToken();

        // Onaylı → panel; değilse doğrulama (onboarding) ekranı
        $driver = Driver::where('user_id', $user->id)->first();
        return redirect()->route(
            $driver && $driver->approval_status === 'approved' ? 'driver.panel' : 'driver.onboarding'
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        // Sadece sürücü guard'ını sıfırla — müşteri oturumu paralel kalır.
        Auth::guard('driver')->logout();
        $request->session()->regenerateToken();
        return redirect()->route('driver.login');
    }

    // ────────────────────────────────────────────────────────────
    // PANEL
    // ────────────────────────────────────────────────────────────

    public function panel(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        // Onaylanmamış sürücü tam paneli göremez → doğrulama (onboarding) ekranına
        if ($driver->approval_status !== 'approved') {
            return redirect()->route('driver.onboarding');
        }

        return view('driver.panel', [
            'driver' => $driver->loadMissing('user', 'currentVehicle.vehicleClass'),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // PROFİL — sürücünün kendi bilgilerini düzenlemesi
    // ────────────────────────────────────────────────────────────

    public function showProfile(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $pendingVehicleRequest = \App\Modules\Driver\Models\DriverChangeRequest::where('driver_id', $driver->id)
            ->where('type', 'vehicle')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        return view('driver.profile', [
            'driver'                 => $driver->loadMissing('user', 'currentVehicle.vehicleClass'),
            'user'                   => $driver->user,
            'vehicle'                => $driver->currentVehicle,
            'vehicleClasses'         => \App\Modules\Vehicle\Models\VehicleClass::where('is_active', true)->orderBy('id')->get(['id', 'slug', 'name']),
            'pendingVehicleRequest'  => $pendingVehicleRequest,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:120'],
            'phone'                 => ['required', 'string', 'max:20'],
            'avatar'                => ['nullable', 'image', 'max:4096'],
            'vehicle_class_id'      => ['nullable', 'integer', 'exists:vehicle_classes,id'],
            'vehicle_brand'         => ['nullable', 'string', 'max:60'],
            'vehicle_model'         => ['nullable', 'string', 'max:60'],
            'vehicle_year'          => ['nullable', 'integer', 'between:1990,2030'],
            'vehicle_color'         => ['nullable', 'string', 'max:30'],
            'vehicle_plate'         => ['nullable', 'string', 'max:15'],
            // AJAX upload sonrası gelen path listesi (yeni yüklenenler)
            'new_photo_paths'       => ['nullable', 'array', 'max:20'],
            'new_photo_paths.*'     => ['string', 'max:255'],
            'remove_photos'         => ['nullable', 'array'],
            'remove_photos.*'       => ['string'],
        ]);

        // 1) Kullanıcı bilgileri
        $userData = [
            'name'  => $validated['name'],
            'phone' => $validated['phone'],
        ];
        if ($request->hasFile('avatar')) {
            if ($driver->user->avatar && ! str_starts_with($driver->user->avatar, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($driver->user->avatar);
            }
            $userData['avatar'] = $request->file('avatar')->store('avatars/drivers', 'public');
        }
        $driver->user->update($userData);

        // 2) Araç bilgileri & fotoğrafları → ADMİN ONAYINA gider (canlıya direkt yansımaz)
        $vehicle = $driver->currentVehicle;
        if ($vehicle) {
            $requestedChanges = [];

            $fieldMap = [
                'vehicle_class_id'    => $validated['vehicle_class_id'] ?? null,
                'brand'               => $validated['vehicle_brand']    ?? null,
                'model'               => $validated['vehicle_model']    ?? null,
                'year_of_manufacture' => $validated['vehicle_year']     ?? null,
                'color'               => $validated['vehicle_color']    ?? null,
                'plate'               => $validated['vehicle_plate']    ?? null,
            ];
            foreach ($fieldMap as $col => $val) {
                if ($val === null || $val === '') continue;
                if ((string) $vehicle->{$col} !== (string) $val) {
                    $requestedChanges[$col] = $val;
                }
            }

            // Foto değişiklikleri (silme + ekleme)
            if (! empty($validated['remove_photos'])) {
                $requestedChanges['remove_photos'] = array_values($validated['remove_photos']);
            }
            if (! empty($validated['new_photo_paths'])) {
                // Güvenlik: sadece bu sürücünün klasöründeki path'leri kabul et
                $prefix = 'vehicle-photos/' . $vehicle->id . '/';
                $newPaths = array_values(array_filter(
                    $validated['new_photo_paths'],
                    fn ($p) => str_starts_with($p, $prefix) && \Illuminate\Support\Facades\Storage::disk('public')->exists($p)
                ));
                if (! empty($newPaths)) $requestedChanges['add_photos'] = $newPaths;
            }

            if (! empty($requestedChanges)) {
                \App\Modules\Driver\Models\DriverChangeRequest::create([
                    'driver_id' => $driver->id,
                    'type'      => 'vehicle',
                    'payload'   => $requestedChanges,
                    'status'    => 'pending',
                ]);

                return redirect()->route('driver.profile')->with('success',
                    'Araç değişiklikleri onaya gönderildi. Süper admin onayladığında müşterilere görünür olur.'
                );
            }
        } elseif ($request->filled('vehicle_class_id') || $request->filled('vehicle_plate') || $request->filled('vehicle_brand')) {
            // 2b) İLK ARAÇ — sürücünün henüz aracı yok. Başvuru onayı araç oluşturmuyor,
            //     bu yüzden ilk aracı burada self-servis oluşturulur + sürücüye bağlanır (doğrudan aktif).
            //     Sonraki DÜZENLEMELER yukarıdaki admin-onaylı change-request akışından geçer
            //     (Easy'den VIP'e sessiz yükseltmeyi engellemek için).
            $vehicleData = $request->validate([
                // Tek-kademe model: sürücü sınıf seçmez; sunucu aktif sınıfı atar.
                'vehicle_class_id' => ['nullable', 'integer', 'exists:vehicle_classes,id'],
                'vehicle_brand'    => ['required', 'string', 'max:60'],
                'vehicle_model'    => ['required', 'string', 'max:60'],
                'vehicle_year'     => ['required', 'integer', 'between:1990,2030'],
                'vehicle_color'    => ['required', 'string', 'max:30'],
                'vehicle_plate'    => ['required', 'string', 'max:20', 'unique:vehicles,plate'],
            ], [], [
                'vehicle_class_id' => 'araç sınıfı',
                'vehicle_brand'    => 'marka',
                'vehicle_model'    => 'model',
                'vehicle_year'     => 'yıl',
                'vehicle_color'    => 'renk',
                'vehicle_plate'    => 'plaka',
            ]);

            $newVehicle = \App\Modules\Vehicle\Models\Vehicle::create([
                'tenant_id'           => $driver->tenant_id,
                'vehicle_class_id'    => $vehicleData['vehicle_class_id'] ?? \App\Modules\Vehicle\Models\VehicleClass::activeDefault()?->id,
                'brand'               => $vehicleData['vehicle_brand'],
                'model'               => $vehicleData['vehicle_model'],
                'year_of_manufacture' => $vehicleData['vehicle_year'],
                'color'               => $vehicleData['vehicle_color'],
                'plate'               => strtoupper(trim($vehicleData['vehicle_plate'])),
                'status'              => 'active',
            ]);
            $driver->update(['current_vehicle_id' => $newVehicle->id]);

            return redirect()->route('driver.profile')->with('success',
                'Aracın tanımlandı ✓ Artık "Müsait" olduğunda müşteri radarında görünebilirsin.'
            );
        }

        return redirect()->route('driver.profile')->with('success', 'Profil güncellendi.');
    }

    /**
     * POST /surucu-paneli/api/vehicle-photo — tek fotoğraf AJAX upload.
     * PHP POST limitini aşmamak için her fotoğraf ayrı istekte gönderilir.
     */
    public function uploadVehiclePhoto(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['success' => false, 'message' => 'Giriş gerekli.'], 401);
        if (! $driver->currentVehicle) {
            return response()->json(['success' => false, 'message' => 'Önce araç tanımla.'], 422);
        }

        $request->validate([
            'photo' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('photo')->store('vehicle-photos/' . $driver->currentVehicle->id, 'public');

        return response()->json([
            'success' => true,
            'path'    => $path,
            'url'     => asset('storage/' . $path),
        ]);
    }

    private const DOCUMENT_TYPES = [
        'license'         => ['column' => 'license_file_path',         'expires' => 'license_expires_at',    'label' => 'Ehliyet'],
        'src'             => ['column' => 'src_file_path',             'expires' => 'src_expires_at',        'label' => 'SRC Sertifikası'],
        'psychotechnic'   => ['column' => 'psychotechnic_file_path',   'expires' => 'psychotechnic_test_at', 'label' => 'Psikoteknik'],
        'criminal_record' => ['column' => 'criminal_record_file_path', 'expires' => 'criminal_record_at',    'label' => 'Adli Sicil'],
        'insurance'       => ['column' => 'insurance_file_path',       'expires' => 'insurance_expires_at',  'label' => 'Sigorta'],
        'inspection'      => ['column' => 'inspection_file_path',      'expires' => 'inspection_expires_at', 'label' => 'Muayene'],
    ];

    /**
     * POST /surucu-paneli/api/document — belge upload (PDF veya resim).
     * Sürücü ehliyet, SRC, psikoteknik, sigorta vs. yükler.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['success' => false, 'message' => 'Giriş gerekli.'], 401);

        $request->validate([
            'type'    => ['required', 'string', 'in:' . implode(',', array_keys(self::DOCUMENT_TYPES))],
            'file'    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'expires' => ['nullable', 'date'],
        ]);

        $type   = $request->input('type');
        $config = self::DOCUMENT_TYPES[$type];

        // Eski dosya varsa sil
        if ($driver->{$config['column']} && ! str_starts_with($driver->{$config['column']}, 'http')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($driver->{$config['column']});
        }

        $path = $request->file('file')->store('driver-documents/' . $driver->id, 'public');

        $update = [$config['column'] => $path];
        if ($request->filled('expires')) {
            $update[$config['expires']] = $request->input('expires');
        }
        $driver->update($update);

        return response()->json([
            'success' => true,
            'path'    => $path,
            'url'     => asset('storage/' . $path),
            'label'   => $config['label'],
        ]);
    }

    /**
     * POST /surucu-paneli/api/document/delete — belgeyi sil.
     */
    public function deleteDocument(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['success' => false, 'message' => 'Giriş gerekli.'], 401);

        $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(self::DOCUMENT_TYPES))],
        ]);

        $config = self::DOCUMENT_TYPES[$request->input('type')];
        if ($driver->{$config['column']} && ! str_starts_with($driver->{$config['column']}, 'http')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($driver->{$config['column']});
        }
        $driver->update([$config['column'] => null]);

        return response()->json(['success' => true]);
    }

    // ────────────────────────────────────────────────────────────
    // API (polled by panel JS)
    // ────────────────────────────────────────────────────────────

    /**
     * GET /surucu-paneli/api/state — panel her 2-3 sn'de buradan veriyi çeker.
     * Tek endpoint = az network gürültüsü.
     */
    public function state(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['authenticated' => false], 401);

        // Aktif yolculuk (kabul ettiğim, henüz tamamlanmamış)
        // Ride.status: completed olduysa aktif sayma
        $activeRequest = RideRequest::query()
            ->with(['acceptedDriver.user', 'ride'])
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->whereHas('ride', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->latest('accepted_at')
            ->first();

        // Yeni teklif sadece aktif yolculuk yokken gösterilir (busy iken atla)
        // İki kaynak: (1) direkt teklif edilenler (pending), (2) havuz teklifleri (pool_expanded)
        $offer = null;
        if (! $activeRequest && $driver->availability_status !== 'busy') {
            // Önce direkt teklif
            $offer = RideRequest::query()
                ->where('offered_driver_id', $driver->id)
                ->where('status', 'pending')
                ->where('offer_expires_at', '>', now())
                ->orderBy('created_at')
                ->first();

            // Direkt yoksa havuz teklifi (pool_expanded ve bu sürücü adaylar listesinde)
            if (! $offer) {
                $offer = RideRequest::query()
                    ->where('status', 'pool_expanded')
                    ->where('offer_expires_at', '>', now())
                    ->whereJsonContains('pool_candidate_driver_ids', $driver->id)
                    ->orderBy('pool_expanded_at')
                    ->first();
            }
        }

        $lastMessageId = (int) request()->query('since_id', 0);
        $messages = [];
        if ($activeRequest) {
            $messages = $activeRequest->messages()
                ->where('id', '>', $lastMessageId)
                ->limit(100)
                ->get(['id', 'sender', 'body', 'created_at'])
                ->map(fn ($m) => [
                    'id'         => $m->id,
                    'sender'     => $m->sender,
                    'body'       => $m->body,
                    'created_at' => $m->created_at->toIso8601String(),
                ])->all();
        }

        // Paket durumu — panel "Paket gerekli" uyarısı için kullanır.
        // Carbon sürüm farkı yaratmamak için kalan süreyi timestamp'ten hesaplıyoruz.
        // Test modu (enforce_packages=false) → banner hiç çıkmasın, remaining=∞.
        $hasPackage   = $driver->hasActivePackage();
        $packageUntil = $driver->package_active_until;
        $enforcePackages = (bool) config('services.driver.enforce_packages', true);
        $remainingMinutes = ! $enforcePackages
            ? 999_999 // test modunda: gerçek paket olsa olmasa "60 dk'dan az" banner'ı çıkmasın
            : (($hasPackage && $packageUntil)
                ? (int) max(0, floor(($packageUntil->getTimestamp() - now()->getTimestamp()) / 60))
                : 0);

        return response()->json([
            'authenticated' => true,
            'driver' => [
                'id'                  => $driver->id,
                'name'                => $driver->user->name,
                'availability_status' => $driver->availability_status,
                'rating'              => (float) $driver->rating,
                'total_rides'         => (int) $driver->total_rides,
            ],
            'package' => [
                'active'            => $hasPackage,
                'expires_at'        => $packageUntil?->toIso8601String(),
                'remaining_minutes' => $remainingMinutes,
            ],
            'offer'  => $offer ? $this->offerPayload($offer) : null,
            'active' => $activeRequest ? $this->activeRequestPayload($activeRequest) : null,
            'messages' => $messages,
            // Faz 6: bu sürücü için açık güvenlik olayı var mı? (forced photo capture tetikleyici)
            'security_incident' => $this->openIncidentPayload($driver),
        ]);
    }

    /**
     * Faz 6 — Sürücüde açık (status=open) bir güvenlik olayı varsa,
     * "Acil — kimlik doğrulama" modal'ı için detay döner.
     */
    private function openIncidentPayload($driver): ?array
    {
        $incident = \App\Modules\Security\Models\SecurityIncident::query()
            ->with('verificationPhotos:id,security_incident_id,type,status')
            ->where('driver_id', $driver->id)
            ->whereIn('status', [
                \App\Modules\Security\Models\SecurityIncident::STATUS_OPEN,
                \App\Modules\Security\Models\SecurityIncident::STATUS_INVESTIGATING,
            ])
            ->latest('created_at')
            ->first();
        if (! $incident) return null;

        $required = [
            \App\Modules\Security\Models\VerificationPhoto::TYPE_SELFIE,
            \App\Modules\Security\Models\VerificationPhoto::TYPE_VEHICLE,
            \App\Modules\Security\Models\VerificationPhoto::TYPE_PLATE,
        ];
        $uploaded = $incident->verificationPhotos->pluck('type')->unique()->all();
        $missing  = array_values(array_diff($required, $uploaded));

        return [
            'public_id'       => $incident->public_id,
            'type'            => $incident->type,
            'status'          => $incident->status,
            'severity'        => $incident->severity,
            'photos_uploaded' => $uploaded,
            'photos_missing'  => $missing,
            'all_uploaded'    => empty($missing),
            'created_at'      => $incident->created_at->toIso8601String(),
        ];
    }

    /**
     * POST /surucu-paneli/api/availability — online/offline toggle.
     */
    public function setAvailability(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        // Onaylanmamış sürücü online olamaz (doğrulama tamamlanmadan yolculuk alamaz)
        if ($driver->approval_status !== 'approved') {
            return response()->json([
                'ok'      => false,
                'code'    => 'not_approved',
                'message' => 'Hesabın henüz onaylanmadı. Doğrulama tamamlanıp onaylanınca müsait olabilirsin.',
                'redirect' => route('driver.onboarding'),
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:online,offline'],
        ]);

        // Online olmak için aktif paket şart — Martı modeli.
        if ($validated['status'] === 'online' && ! $driver->hasActivePackage()) {
            return response()->json([
                'ok'      => false,
                'code'    => 'package_required',
                'message' => 'Online olmak için aktif paket gerekli. Paketler sayfasından satın al.',
                'redirect' => route('driver.packages.index'),
            ], 422);
        }

        // 'busy' zaten aktif yolculuktan otomatik kuruluyor — sürücü el ile değiştiremez
        if ($driver->availability_status !== 'busy') {
            $driver->update(['availability_status' => $validated['status']]);
        }

        return response()->json(['ok' => true, 'status' => $driver->fresh()->availability_status]);
    }

    /**
     * POST /surucu-paneli/api/location — web panel canlı GPS güncellemesi.
     * Sürücü online iken tarayıcıdan periyodik gönderilir (mobil updateLocation'ın web karşılığı).
     * current_lat/lng güncel tutulur ki radar haritasında doğru yerde görünsün + mesafe/ETA gerçek hesaplansın.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Rate limit: konum 5 sn'den hızlı güncellenmesin (pil + sunucu koruması)
        $rl = 'driver_loc:' . $driver->id;
        if (RateLimiter::tooManyAttempts($rl, 1)) {
            return response()->json(['ok' => true, 'throttled' => true]);
        }
        RateLimiter::hit($rl, 5);

        $driver->update([
            'current_lat'              => (float) $validated['lat'],
            'current_lng'              => (float) $validated['lng'],
            'last_location_updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /surucu-paneli/api/women-only — "Sadece kadın yolcu al" tercihi.
     * Yalnızca kadın sürücüler kullanabilir (güvenlik özelliği).
     */
    public function setWomenOnly(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

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
     * POST /surucu-paneli/api/offers/{publicId}/accept
     */
    public function acceptOffer(string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        // Pool expanded ise → DispatcherService::acceptByPoolDriver
        // (status=pool_expanded, ilk kabul eden alır, müşteri reconfirm akışı başlar)
        if ($req->status === 'pool_expanded') {
            $ok = app(DispatcherService::class)
                ->acceptByPoolDriver($req, $driver);
            if (! $ok) {
                return response()->json(['ok' => false, 'message' => 'Bu talep artık geçerli değil.'], 409);
            }
            return response()->json([
                'ok' => true,
                'awaiting_customer_reconfirm' => true,
                'message' => 'Talep alındı, müşteri onayı bekleniyor.',
            ]);
        }

        // Normal akış (status=pending, doğrudan accept) — sürücü YOLCUNUN teklifini kabul eder
        $agreed = $req->customer_offer_fare !== null ? (float) $req->customer_offer_fare : null;
        try {
            $this->service->accept($req, $driver, $agreed);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /surucu-paneli/api/offers/{publicId}/reject
     */
    public function rejectOffer(string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        // Pool reject → rejected listesine ekle
        if ($req->status === 'pool_expanded') {
            app(DispatcherService::class)
                ->rejectByPoolDriver($req, $driver);
            return response()->json(['ok' => true]);
        }

        $this->service->reject($req, $driver);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /surucu-paneli/api/offers/{publicId}/counter
     * Body: { amount } — sürücü yolcunun teklifine karşı fiyat verir.
     * 1:1 (pending) veya havuz (pool_expanded) fazında çalışır.
     */
    public function counterOffer(Request $request, string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        // Havuz teklifi ise dispatcher üzerinden (ilk cevaplayan kilidi alır)
        if ($req->status === 'pool_expanded') {
            $ok = app(DispatcherService::class)
                ->counterByPoolDriver($req, $driver, (float) $validated['amount']);
            return response()->json(
                $ok
                    ? ['ok' => true, 'awaiting_customer_reconfirm' => true, 'message' => 'Karşı teklif iletildi, müşteri onayı bekleniyor.']
                    : ['ok' => false, 'message' => 'Bu talep artık geçerli değil.'],
                $ok ? 200 : 409
            );
        }

        $result = $this->service->driverCounter($req, $driver, (float) $validated['amount']);
        return response()->json(array_merge(['ok' => $result['ok']], $result), $result['ok'] ? 200 : 422);
    }

    /**
     * POST /surucu-paneli/api/active/message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $req = RideRequest::query()
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        $msg = RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'driver',
            'body'            => $validated['body'],
        ]);

        // Web'den sürücü mesajı → müşteriye push (mobil parite, best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->newMessage($req, 'driver', $validated['body']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[DriverPanelController] message push', ['err' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'message' => [
                'id'         => $msg->id,
                'sender'     => $msg->sender,
                'body'       => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /surucu-paneli/api/active/complete — yolculuk tamamlandı.
     */
    public function completeRide(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::query()
            ->with('ride')
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        if ($req->ride) {
            $req->ride->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }
        $req->update(['completed_at' => now()]);
        $driver->update(['availability_status' => 'online']);
        $driver->increment('total_rides');

        // Trust skoruna pozitif yansı
        $this->trustService->recordRideCompleted($req->customer_phone);

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Yolculuk tamamlandı. Geri bildirim için teşekkürler.',
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Faz 5 — TUZAK SORU
     * POST /surucu-paneli/api/active/boarding-question
     * Sürücü kapıya geldikten sonra "Müşteri araca bindi mi?" sorusunu açar.
     * Bu endpoint sadece soru zamanını işaretler — yanıt /boarding-confirm ile gelir.
     */
    public function openBoardingQuestion(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::query()
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        if (! $req->driver_arrived_at) {
            return response()->json(['ok' => false, 'message' => 'Önce "Vardım" butonuna bas.'], 422);
        }

        if (! $req->boarding_question_at) {
            $req->update(['boarding_question_at' => now()]);
        }

        return response()->json(['ok' => true, 'question_at' => $req->boarding_question_at?->toIso8601String()]);
    }

    /**
     * Faz 5 — TUZAK CEVABI
     * POST /surucu-paneli/api/active/boarding-confirm
     * Sürücü "EVET, müşteri araca bindi" der.
     * Bu butona basınca sadece tarih işaretlenir — yolculuk ASLA bu noktada
     * başlatılmaz. Sonraki adım "Yolculuğu Başlat" butonudur.
     */
    public function confirmBoarding(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::query()
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        if (! $req->boarding_question_at) {
            return response()->json(['ok' => false, 'message' => 'Tuzak soru henüz açılmadı.'], 422);
        }

        if (! $req->boarding_confirmed_at) {
            $req->update(['boarding_confirmed_at' => now()]);
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Sürücü, müşterinin araca bindiğini bildirdi. Yolculuk başlatma onayı bekleniyor.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'boarding_confirmed_at' => $req->boarding_confirmed_at->toIso8601String(),
            'message' => 'Şimdi YOLCULUĞU BAŞLAT butonuna basabilirsin.',
        ]);
    }

    /**
     * Faz 5 — YOLCULUĞU BAŞLAT (fiili başlatma)
     * POST /surucu-paneli/api/active/start-ride
     * Sürücü sarı "YOLCULUĞU BAŞLAT" butonuna basınca:
     *   - ride_request.started_at set edilir
     *   - ride.status = 'in_progress', started_at set
     *   - Müşteri tarafına push: görsel doğrulama modal'ı açılır (Faz 6)
     */
    public function startRide(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::query()
            ->with('ride')
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        if (! $req->boarding_confirmed_at) {
            return response()->json(['ok' => false, 'message' => 'Önce müşterinin araca bindiğini onayla.'], 422);
        }

        if ($req->started_at) {
            return response()->json(['ok' => true, 'already_started' => true]);
        }

        $req->update([
            'started_at' => now(),
            'visual_verify_prompted_at' => now(), // müşteri tarafına Faz 6 modal'ı tetiklenir
        ]);

        if ($req->ride) {
            $req->ride->update([
                'status'     => 'in_progress',
                'started_at' => now(),
            ]);
        }

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => '🚗 Yolculuk başladı. İyi yolculuklar!',
        ]);

        return response()->json([
            'ok' => true,
            'started_at' => $req->started_at->toIso8601String(),
        ]);
    }

    /**
     * POST /surucu-paneli/api/active/arrived
     * Sürücü "alış noktasına vardım" işaretler. 5 dk sonra no-show butonu açılır.
     */
    public function markArrived(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::query()
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        $result = $this->noShowService->markDriverArrived($req, $driver);
        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * POST /surucu-paneli/api/active/no-show
     * Sürücü "müşteri gelmedi" basar. Body: { lat?, lng?, note? }
     */
    public function reportNoShow(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $validated = $request->validate([
            'lat'  => ['nullable', 'numeric', 'between:-90,90'],
            'lng'  => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $req = RideRequest::query()
            ->with('ride')
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

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

    // ────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────

    private function currentDriver(): ?Driver
    {
        // SÜRÜCÜ guard'ı kullan — müşteri guard'ından bağımsız.
        $user = Auth::guard('driver')->user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }

    /**
     * Yolcu kimliği: adı (Ad + Soyad baş harfi) ve avatar'ı.
     * Kayıtlı bir User varsa oradan (güncel + doğru), yoksa RideRequest snapshot'ından.
     * KVKK: soyadı kısaltılır ("Ferdi Korkmaz" → "Ferdi K.") — telefon numarası hiç gönderilmez;
     * arama tarayıcı içi WebRTC ile yapılır.
     *
     * @return array{name: string, avatar_url: ?string}
     */
    private function resolveCustomerIdentity(RideRequest $req): array
    {
        $user = null;
        if ($req->customer_phone) {
            $user = User::where('phone', $req->customer_phone)
                ->where('type', 'customer')
                ->first();
        }
        $fullName = trim((string) ($user?->name ?: $req->customer_name));
        if ($fullName === '') {
            $displayName = 'Müşteri';
        } else {
            $parts = preg_split('/\s+/', $fullName);
            $displayName = count($parts) > 1
                ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
                : $fullName;
        }

        $avatar = $user?->avatar
            ? \Illuminate\Support\Facades\Storage::url($user->avatar)
            : null;

        return ['name' => $displayName, 'avatar_url' => $avatar];
    }

    private function offerPayload(RideRequest $req): array
    {
        $identity = $this->resolveCustomerIdentity($req);

        return [
            'public_id'           => $req->public_id,
            'customer_name'       => $identity['name'],
            'customer_avatar_url' => $identity['avatar_url'],
            'pickup_address'      => $req->pickup_address,
            'dropoff_address'     => $req->dropoff_address,
            'distance_km'         => (float) $req->distance_km,
            'duration_minutes'    => (int) $req->duration_minutes,
            'estimated_fare'      => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'expires_at'          => $req->offer_expires_at?->toIso8601String(),
            'seconds_remaining'   => max(0, (int) round(now()->diffInSeconds($req->offer_expires_at, false))),
            // Fiyat pazarlığı — sürücü yolcunun teklifini görür, counter atabilir
            'negotiation'         => $this->negotiationPayload($req),
        ];
    }

    private function activeRequestPayload(RideRequest $req): array
    {
        $trust = $this->trustService->getOrCreate($req->customer_phone);
        $identity = $this->resolveCustomerIdentity($req);

        $arrivedAt = $req->driver_arrived_at;
        $waitSec   = $arrivedAt ? abs((int) $arrivedAt->diffInSeconds(now())) : 0;
        $noShowReady = $arrivedAt && $waitSec >= NoShowService::MIN_WAIT_SECONDS;
        $noShowCountdown = $arrivedAt ? max(0, NoShowService::MIN_WAIT_SECONDS - $waitSec) : null;

        return [
            'public_id'             => $req->public_id,
            'customer_name'         => $identity['name'],
            'customer_avatar_url'   => $identity['avatar_url'],
            // customer_phone KALDIRILDI — KVKK: sürücü numarayı görmez, arama WebRTC üzerinden yapılır.
            'customer_trust_label'  => $trust->trustLabel(),
            'customer_is_new'       => $trust->isNewCustomer(),
            'customer_completed_rides' => (int) $trust->total_completed,
            'customer_no_shows'     => (int) $trust->total_no_shows,
            'pickup_address'        => $req->pickup_address,
            'pickup_lat'            => (float) $req->pickup_lat,
            'pickup_lng'            => (float) $req->pickup_lng,
            'dropoff_address'       => $req->dropoff_address,
            'distance_km'           => (float) $req->distance_km,
            'duration_minutes'      => (int) $req->duration_minutes,
            'estimated_fare'        => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'accepted_at'           => $req->accepted_at?->toIso8601String(),
            'arrived_at'            => $arrivedAt?->toIso8601String(),
            'customer_confirmed_at' => $req->customer_confirmed_at?->toIso8601String(),
            'no_show_button_ready'  => $noShowReady,
            'no_show_countdown_sec' => $noShowCountdown,
            'ride_status'           => $req->ride?->status,
            // Faz 5 — tuzak soru + ride start akışı
            'boarding_question_at'  => $req->boarding_question_at?->toIso8601String(),
            'boarding_confirmed_at' => $req->boarding_confirmed_at?->toIso8601String(),
            'started_at'            => $req->started_at?->toIso8601String(),
            // Faz 6 — görsel doğrulama
            'visual_verified_at'    => $req->visual_verified_at?->toIso8601String(),
            'visual_verify_failed_at' => $req->visual_verify_failed_at?->toIso8601String(),
        ];
    }
}
