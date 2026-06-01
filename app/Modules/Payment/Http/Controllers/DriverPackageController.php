<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Models\DriverPackage;
use App\Modules\Payment\Services\DriverPackageService;
use App\Modules\Payment\Services\GatewayFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DriverPackageController extends Controller
{
    public function __construct(
        private DriverPackageService $packages,
    ) {}

    private function currentDriver(): ?Driver
    {
        $user = Auth::user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }

    /**
     * GET /surucu-paneli/paketler — paket katalogu + saklı kartlar.
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

        // iyzico saklı kartlar — sadece IYZICO_ENABLED=true iken anlamlı
        $savedCards = [];
        $cardUserKey = $driver->user?->iyzico_card_user_key;
        if ($cardUserKey) {
            try {
                $savedCards = GatewayFactory::make()->listSavedCards($cardUserKey);
            } catch (\Throwable $e) {
                // iyzico down ise sayfa açılmasın engellenemez
                report($e);
            }
        }

        return view('driver.packages', [
            'driver'        => $driver,
            'catalog'       => $catalog,
            'activePackage' => $activePackage,
            'history'       => $history,
            'savedCards'    => $savedCards,
        ]);
    }

    /**
     * POST /surucu-paneli/paketler/satin-al — yeni kart ile (Checkout Form).
     * Sürücüyü iyzico (veya mock) checkout sayfasına yönlendirir.
     * Saklı kart varsa cardUserKey gönderir → iyzico sayfasında listelenir.
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
        $cardUserKey = $driver->user?->iyzico_card_user_key;
        $checkout    = GatewayFactory::make()->initCheckout($package, $callbackUrl, $cardUserKey);

        if (empty($checkout['redirect_url'])) {
            $this->packages->markFailed($package, 'Gateway başlatılamadı', $checkout['raw'] ?? []);
            return redirect()->route('driver.packages.index')
                ->with('error', 'Ödeme sayfası açılamadı. Lütfen tekrar deneyin.');
        }

        $package->update([
            'payment_reference' => $checkout['token'] ?? null,
            'conversation_id'   => $checkout['conversation_id'] ?? null,
            'payment_meta'      => $checkout['raw'] ?? [],
        ]);

        return redirect()->away($checkout['redirect_url']);
    }

    /**
     * POST /surucu-paneli/paketler/hizli-satin-al — saklı kart ile tek tıkla ödeme.
     * 3D Secure HTML döner → 3ds sayfasında iframe içinde render edilir.
     */
    public function quickPurchase(Request $request): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $validated = $request->validate([
            'type'       => ['required', 'string', 'in:' . implode(',', array_keys(config('packages.types')))],
            'card_token' => ['required', 'string', 'max:100'],
        ]);

        try {
            $result = $this->packages->startSavedCardPayment(
                $driver,
                $validated['type'],
                $validated['card_token'],
                fn (DriverPackage $p) => route('driver.packages.threeds_callback', ['package' => $p->id]),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('driver.packages.index')
                ->with('error', collect($e->errors())->flatten()->first() ?: 'Hata oluştu.');
        }

        if ($result['error'] || empty($result['html_content'])) {
            return redirect()->route('driver.packages.index')
                ->with('error', $result['error'] ?: 'Hızlı ödeme başlatılamadı.');
        }

        // Gerçek callback URL'ini paket id'siyle güncelle (3DS request'inde gönderildi)
        $package = $result['package'];

        return view('driver.packages_3ds', [
            'package'     => $package,
            'htmlContent' => $result['html_content'],
        ]);
    }

    /**
     * POST|GET /surucu-paneli/paketler/{package}/callback — Checkout Form sonrası.
     */
    public function callback(Request $request, DriverPackage $package): RedirectResponse
    {
        $driver = $this->currentDriver();
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
     * POST /surucu-paneli/paketler/{package}/3ds-callback — saklı kart 3D doğrulamadan dönüş.
     * iyzico burayı POST'lar; biz `/payment/3dsecure/auth` ile sonucu finalize ederiz.
     */
    public function threeDsCallback(Request $request, DriverPackage $package): View|RedirectResponse
    {
        $gateway = GatewayFactory::make();
        $result  = $gateway->complete3dPayment($request->all());

        if (! $result->success) {
            $this->packages->markFailed($package, $result->errorMessage ?? '3D doğrulama başarısız', $result->raw);
            // 3DS iframe içinden geldiğimiz için parent'a yönlendirme yapan ufak HTML
            return view('driver.packages_3ds_result', [
                'success' => false,
                'message' => $result->errorMessage ?: '3D doğrulama başarısız',
            ]);
        }

        $this->packages->markPaidAndActivate($package, $result->reference ?? 'OK', $result->raw);

        return view('driver.packages_3ds_result', [
            'success' => true,
            'message' => $package->label() . ' paketi aktive edildi.',
        ]);
    }

    /**
     * POST /surucu-paneli/kartlar/sil — iyzico'daki saklı kartı sil.
     */
    public function deleteCard(Request $request): RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $validated = $request->validate([
            'card_token' => ['required', 'string', 'max:100'],
        ]);

        $cardUserKey = $driver->user?->iyzico_card_user_key;
        if (! $cardUserKey) {
            return redirect()->route('driver.packages.index')
                ->with('error', 'Saklı kart bulunamadı.');
        }

        $ok = GatewayFactory::make()->deleteSavedCard($cardUserKey, $validated['card_token']);

        return redirect()->route('driver.packages.index')
            ->with($ok ? 'success' : 'error', $ok ? 'Kart silindi.' : 'Kart silinemedi.');
    }

    /**
     * GET /surucu-paneli/paketler/{package}/mock — mock checkout (IYZICO_ENABLED=false).
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
