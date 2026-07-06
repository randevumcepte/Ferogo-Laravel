<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Models\CustomerTrust;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\FavoriteDriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerPanelController extends Controller
{
    public function __construct(
        private CustomerTrustService $trustService,
        private FavoriteDriverService $favoriteService,
    ) {}

    /**
     * GET /musteri-giris — telefon + OTP giriş ekranı.
     * ?return=<path> — login sonrası bu sayfaya dön (default: /musteri-paneli).
     * Güvenlik: yalnızca relative path kabul edilir (open-redirect koruması).
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        $return = $this->safeReturnUrl($request->query('return'));

        if ($this->currentCustomer()) {
            return redirect($return ?: route('customer.panel'));
        }

        return view('customer.login', ['returnUrl' => $return]);
    }

    /**
     * Sadece kendi domain'imizdeki relative path'leri kabul et.
     * Açık yönlendirme (open redirect) koruması.
     */
    private function safeReturnUrl(?string $url): ?string
    {
        if (! $url) return null;
        if (! str_starts_with($url, '/')) return null;
        if (str_starts_with($url, '//')) return null;
        return $url;
    }

    /**
     * GET /musteri-paneli — login zorunlu, müşteri dashboard.
     */
    public function panel(): View|RedirectResponse
    {
        $user = $this->currentCustomer();
        if (! $user) {
            return redirect()->route('customer.login');
        }

        $trust = $this->trustService->getOrCreate($user->phone ?? '');

        // Aktif yolculuk: en güncel pending/accepted/driver_arriving in_progress ride
        $activeRide = Ride::query()
            ->with(['driver.user', 'driver.currentVehicle.vehicleClass', 'vehicleClass'])
            ->where('customer_user_id', $user->id)
            ->whereIn('status', ['pending', 'searching', 'assigned', 'driver_arriving', 'in_progress'])
            ->latest('created_at')
            ->first();

        // Accepted (Ride'a bağlanmış) talepleri customer_user_id üzerinden bul.
        // ÖNEMLİ: Ride tamamlanmış/iptal olmuşsa "aktif" sayma — RideRequest.status
        // 'accepted' kalır ama yolculuk bitmiştir, Son Yolculuklar listesine geçer.
        $activeRequest = RideRequest::query()
            ->with([
                'acceptedDriver.user',
                'acceptedDriver.currentVehicle.vehicleClass',
                'ride.vehicleClass',
            ])
            ->where('status', 'accepted')
            ->whereHas('ride', fn ($q) => $q
                ->where('customer_user_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            )
            ->latest('id')
            ->first();

        // Son 10 yolculuk
        $recentRides = Ride::query()
            ->with(['driver.user', 'vehicleClass'])
            ->where('customer_user_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Favori sürücüler ("tekrar onu çağır") + hızlı id seti (kalpleri işaretlemek için)
        $favoriteDrivers = $this->favoriteService->listForUser($user);
        $favoriteIds     = $this->favoriteService->favoriteIds($user);

        return view('customer.panel', [
            'user'           => $user,
            'trust'          => $trust,
            'activeRide'     => $activeRide,
            'activeRequest'  => $activeRequest,
            'recentRides'    => $recentRides,
            'favoriteDrivers'=> $favoriteDrivers,
            'favoriteIds'    => $favoriteIds,
        ]);
    }

    /**
     * POST /musteri-paneli/favori/{driverId} — favori ekle/çıkar (toggle).
     * JSON döner: { ok, favorited, message }.
     */
    public function toggleFavorite(int $driverId): JsonResponse
    {
        $user = $this->currentCustomer();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Giriş gerekli.'], 401);
        }

        $result = $this->favoriteService->toggle($user, $driverId);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * POST /musteri-cikis
     *
     * Sadece MÜŞTERİ guard'ını sıfırla — paralel sürücü oturumuna dokunma.
     * session()->invalidate() KULLANMA (sürücü session'ını da yok eder).
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();
        return redirect()->route('customer.login');
    }

    /**
     * GET /musteri-paneli/api/state — küçük polling (aktif yolculuk durumu).
     * RideRequest.customer_phone formatı UI'den geldiği gibi (boşluklu olabilir),
     * User.phone normalize edilmiş hali — bu yüzden son N aktif kayıt çekip
     * PHP'de normalize edip karşılaştırıyoruz.
     */
    public function state(): JsonResponse
    {
        $user = $this->currentCustomer();
        if (! $user) return response()->json(['authenticated' => false], 401);

        $candidates = RideRequest::query()
            ->with(['acceptedDriver.user', 'ride'])
            ->whereIn('status', ['pending', 'accepted'])
            ->latest('id')
            ->limit(20)
            ->get();

        $activeRequest = $candidates->first(function ($r) use ($user) {
            if ($this->trustService->normalizePhone($r->customer_phone) !== $user->phone) return false;
            // Ride tamamlanmış/iptal olmuşsa aktif sayma — Son Yolculuklar'a geçer.
            if ($r->ride && in_array($r->ride->status, ['completed', 'cancelled', 'no_show'], true)) return false;
            return true;
        });

        return response()->json([
            'authenticated' => true,
            'active'        => $activeRequest ? [
                'public_id' => $activeRequest->public_id,
                'status'    => $activeRequest->status,
                'ride_id'   => $activeRequest->ride?->public_id,
            ] : null,
        ]);
    }

    /**
     * GET /musteri-paneli/profil — profil sayfası (görsel kart)
     */
    public function showProfile(): View|RedirectResponse
    {
        $user = $this->currentCustomer();
        if (! $user) return redirect()->route('customer.login');

        $trust = $this->trustService->getOrCreate($user->phone ?? '');

        // Üyelikten beri geçen süre
        $memberSince = $user->created_at;
        $memberDays  = $memberSince ? (int) $memberSince->diffInDays(now()) : 0;

        $totalRides    = \App\Modules\Booking\Models\Ride::where('customer_user_id', $user->id)->count();
        $completedRides = \App\Modules\Booking\Models\Ride::where('customer_user_id', $user->id)
            ->where('status', 'completed')->count();

        return view('customer.profile', [
            'user'           => $user,
            'trust'          => $trust,
            'memberSince'    => $memberSince,
            'memberDays'     => $memberDays,
            'totalRides'     => $totalRides,
            'completedRides' => $completedRides,
        ]);
    }

    /**
     * POST /musteri-paneli/profil — isim + avatar güncelleme
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->currentCustomer();
        if (! $user) return redirect()->route('customer.login');

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $updates = ['name' => $validated['name']];

        if ($request->boolean('remove_avatar') && $user->avatar) {
            if (! str_starts_with($user->avatar, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $updates['avatar'] = null;
        } elseif ($request->hasFile('avatar')) {
            // Eski avatar sil
            if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $updates['avatar'] = $request->file('avatar')->store('avatars/customers', 'public');
        }

        $user->update($updates);

        return redirect()->route('customer.profile')->with('success', 'Profil güncellendi.');
    }

    /**
     * GET /musteri-paneli/profil/verilerimi-indir — KVKK veri indirme (JSON)
     */
    public function downloadData()
    {
        $user = $this->currentCustomer();
        if (! $user) return redirect()->route('customer.login');

        $rides = \App\Modules\Booking\Models\Ride::where('customer_user_id', $user->id)
            ->with('vehicleClass')
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'created_at'        => $r->created_at?->toIso8601String(),
                'status'            => $r->status,
                'pickup_address'    => $r->pickup_address,
                'dropoff_address'   => $r->dropoff_address,
                'distance_km'       => $r->estimated_distance_km,
                'total_fare'        => $r->total_fare,
                'vehicle_class'     => $r->vehicleClass?->name,
            ]);

        $trust = $this->trustService->getOrCreate($user->phone ?? '');

        $payload = [
            'export_date' => now()->toIso8601String(),
            'user' => [
                'name'              => $user->name,
                'phone'             => $user->phone,
                'email'             => $user->email,
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'created_at'        => $user->created_at?->toIso8601String(),
            ],
            'trust' => [
                'trust_score'                  => $trust->trust_score,
                'total_requests'               => $trust->total_requests,
                'total_completed'              => $trust->total_completed,
                'total_no_shows'               => $trust->total_no_shows,
                'total_customer_cancellations' => $trust->total_customer_cancellations,
            ],
            'rides' => $rides,
        ];

        $filename = 'ferxgo-verilerim-' . now()->format('Y-m-d') . '.json';
        return response()
            ->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * POST /musteri-paneli/profil/hesabi-sil — KVKK hesap silme
     * Soft delete: kullanıcının kişisel alanları temizlenir, ride history korunur
     * (yasal mali kayıt zorunluluğu — Ride'lar tutulur ama anonimleştirilir).
     */
    public function deleteAccount(Request $request): RedirectResponse
    {
        $user = $this->currentCustomer();
        if (! $user) return redirect()->route('customer.login');

        $request->validate([
            'confirm' => ['required', 'in:SIL'],
        ], [
            'confirm.required' => 'Onay alanına SİL yazmalısın.',
            'confirm.in'       => 'Onay alanına büyük harflerle SİL yazmalısın.',
        ]);

        // Avatar sil
        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
        }

        // Kişisel bilgiler anonimleştir, hesabı pasifleştir
        $user->update([
            'name'              => 'Silinmiş Kullanıcı #' . $user->id,
            'email'             => 'deleted-' . $user->id . '@ferogo.local',
            'phone'             => null,
            'tc_no'             => null,
            'birth_date'        => null,
            'gender'            => null,
            'avatar'            => null,
            'phone_verified_at' => null,
            'status'            => 'suspended',
            'password'          => bcrypt(\Illuminate\Support\Str::random(60)),
        ]);

        // Sadece müşteri guard'ını sıfırla — sürücü oturumu paralel kalır.
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Hesabın silindi. Yolculuk geçmişi yasal sebeplerle anonim olarak saklanır.');
    }

    /**
     * GET /musteri-paneli/api/active-tracking
     * Sürücü yoldaysa canlı konum + ETA + mesafe — kart polling'i için.
     * Hedef: ride status driver_arriving ise pickup, in_progress ise dropoff.
     */
    public function activeTracking(): JsonResponse
    {
        $user = $this->currentCustomer();
        if (! $user) return response()->json(['authenticated' => false], 401);

        $ride = Ride::query()
            ->with('driver:id,user_id,current_lat,current_lng,last_location_updated_at')
            ->where('customer_user_id', $user->id)
            ->whereIn('status', ['driver_arriving', 'in_progress', 'assigned'])
            ->latest('id')
            ->first();

        if (! $ride || ! $ride->driver) {
            return response()->json(['success' => true, 'tracking' => null]);
        }

        $dLat = (float) ($ride->driver->current_lat ?? 0);
        $dLng = (float) ($ride->driver->current_lng ?? 0);
        if ($dLat === 0.0 || $dLng === 0.0) {
            return response()->json(['success' => true, 'tracking' => null]);
        }

        $pLat = (float) $ride->pickup_lat;
        $pLng = (float) $ride->pickup_lng;
        $dropoffLat = (float) $ride->dropoff_lat;
        $dropoffLng = (float) $ride->dropoff_lng;

        // driver_arriving → hedef pickup; in_progress → hedef dropoff
        if ($ride->status === 'in_progress' && $dropoffLat && $dropoffLng) {
            $targetLat = $dropoffLat;
            $targetLng = $dropoffLng;
            $targetLabel = 'dropoff';
        } else {
            $targetLat = $pLat;
            $targetLng = $pLng;
            $targetLabel = 'pickup';
        }

        $distance = $this->haversineKm($dLat, $dLng, $targetLat, $targetLng);
        // Şehir içi ortalama: 1 km ~ 2.4 dk + sabit 0.8 dk reaktif buffer
        $etaMinutes = max(1, (int) round($distance * 2.4 + 0.8));

        return response()->json([
            'success' => true,
            'tracking' => [
                'ride_id'      => $ride->id,
                'ride_status'  => $ride->status,
                'driver_lat'   => $dLat,
                'driver_lng'   => $dLng,
                'target_lat'   => $targetLat,
                'target_lng'   => $targetLng,
                'target_kind'  => $targetLabel,
                'distance_km'  => round($distance, 2),
                'eta_minutes'  => $etaMinutes,
                'last_updated' => $ride->driver->last_location_updated_at?->toIso8601String(),
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

    private function currentCustomer(): ?User
    {
        // MÜŞTERİ guard'ı kullan — sürücü guard'ından bağımsız.
        $user = Auth::guard('customer')->user();
        if (! $user || $user->type !== 'customer') return null;
        return $user;
    }
}
