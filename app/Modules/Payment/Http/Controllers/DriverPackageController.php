<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Models\DriverPackage;
use App\Modules\Payment\Services\DriverPackageService;
use App\Modules\Payment\Services\GatewayFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DriverPackageController extends Controller
{
    public function __construct(
        private DriverPackageService $packages,
    ) {}

    /**
     * Aktif sürücünün hesabına bağlı Driver kaydı.
     * (DriverPanelController'daki currentDriver() ile aynı mantık)
     */
    private function currentDriver(): ?Driver
    {
        $user = Auth::user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }

    /**
     * GET /surucu-paneli/paketler — kademeli paket katalogu + sürücünün aktif paketi.
     */
    public function index(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $catalog = collect(config('packages.types'))
            ->map(fn ($def, $key) => array_merge($def, ['key' => $key]))
            ->sortBy('order')
            ->values()
            ->all();

        $activePackage = $driver->activePackage()->first();
        $history = DriverPackage::where('driver_id', $driver->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('driver.packages', [
            'driver'        => $driver,
            'catalog'       => $catalog,
            'activePackage' => $activePackage,
            'history'       => $history,
        ]);
    }

    /**
     * POST /surucu-paneli/paketler/satin-al — gateway'i başlatır,
     * sürücüyü iyzico (veya mock) checkout sayfasına yönlendirir.
     */
    public function purchase(Request $request): RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(config('packages.types')))],
        ]);

        $package = $this->packages->createPurchase($driver, $validated['type']);

        $callbackUrl = route('driver.packages.callback', ['package' => $package->id]);
        $checkout    = GatewayFactory::make()->initCheckout($package, $callbackUrl);

        if (empty($checkout['redirect_url'])) {
            $this->packages->markFailed($package, 'Gateway başlatılamadı', $checkout['raw'] ?? []);
            return redirect()->route('driver.packages.index')
                ->with('error', 'Ödeme sayfası açılamadı. Lütfen tekrar deneyin.');
        }

        $package->update([
            'payment_reference' => $checkout['token'] ?? null,
            'payment_meta'      => $checkout['raw'] ?? [],
        ]);

        return redirect()->away($checkout['redirect_url']);
    }

    /**
     * GET|POST /surucu-paneli/paketler/{package}/callback — iyzico'dan dönüş.
     * iyzico POST eder; mock provider de POST eder.
     */
    public function callback(Request $request, DriverPackage $package): RedirectResponse
    {
        $driver = $this->currentDriver();

        // Güvenlik: callback geldiğinde sürücü oturumu varsa, paket onun mu?
        if ($driver && $package->driver_id !== $driver->id) {
            abort(403);
        }

        $gateway = GatewayFactory::make();
        $result  = $gateway->verifyCallback($request->all());

        if (! $result->success) {
            $this->packages->markFailed($package, $result->errorMessage ?? 'unknown', $result->raw);
            return redirect()->route('driver.packages.index')
                ->with('error', $result->errorMessage ?: 'Ödeme onaylanmadı.');
        }

        $this->packages->markPaidAndActivate($package, $result->reference ?? 'OK', $result->raw);

        return redirect()->route('driver.packages.index')
            ->with('success', $package->label() . ' paket aktive edildi. İyi yolculuklar!');
    }

    /**
     * GET /surucu-paneli/paketler/{package}/mock — mock provider'ın "fake checkout" sayfası.
     * Sadece IYZICO_ENABLED=false iken kullanılır.
     */
    public function mockCheckout(Request $request, DriverPackage $package): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');
        if ($package->driver_id !== $driver->id) abort(403);

        return view('driver.packages_mock_checkout', [
            'package'  => $package,
            'token'    => $request->query('token', 'MOCK'),
            'callback' => route('driver.packages.callback', ['package' => $package->id]),
        ]);
    }
}
