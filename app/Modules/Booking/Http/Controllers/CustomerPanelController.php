<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Models\CustomerTrust;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\CustomerTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerPanelController extends Controller
{
    public function __construct(
        private CustomerTrustService $trustService,
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

        return view('customer.panel', [
            'user'         => $user,
            'trust'        => $trust,
            'activeRide'   => $activeRide,
            'activeRequest'=> $activeRequest,
            'recentRides'  => $recentRides,
        ]);
    }

    /**
     * POST /musteri-cikis
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
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
        $user = Auth::user();
        if (! $user || $user->type !== 'customer') return null;
        return $user;
    }
}
