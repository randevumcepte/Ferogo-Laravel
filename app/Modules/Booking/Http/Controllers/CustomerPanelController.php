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

        // Accepted (Ride'a bağlanmış) talepleri customer_user_id üzerinden bul
        // Tüm sürücü/araç detayları kartta gözüksün diye geniş eager-load.
        $activeRequest = RideRequest::query()
            ->with([
                'acceptedDriver.user',
                'acceptedDriver.currentVehicle.vehicleClass',
                'ride.vehicleClass',
            ])
            ->where('status', 'accepted')
            ->whereHas('ride', fn ($q) => $q->where('customer_user_id', $user->id))
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
            return $this->trustService->normalizePhone($r->customer_phone) === $user->phone;
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

    private function currentCustomer(): ?User
    {
        $user = Auth::user();
        if (! $user || $user->type !== 'customer') return null;
        return $user;
    }
}
