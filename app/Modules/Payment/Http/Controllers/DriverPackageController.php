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
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DriverPackageController extends Controller
{
    public function __construct(
        private DriverPackageService $packages,
    ) {}

    private function currentDriver(): ?Driver
    {
        $user = Auth::guard('driver')->user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }

    /**
     * GET /surucu-paneli/paketler — paket katalogu + aktif paket + geçmiş.
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
     * POST /surucu-paneli/paketler/satin-al — paket seç, PayTR token al,
     * sürücüyü iframe sayfasına yönlendir.
     */
    public function purchase(Request $request): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(config('packages.types')))],
        ]);

        $package = $this->packages->createPurchase($driver, $validated['type']);

        $checkout = GatewayFactory::make()->initCheckout($package);

        if (empty($checkout['iframe_url'])) {
            $this->packages->markFailed($package, $checkout['error'] ?? 'Token alınamadı', $checkout['raw'] ?? []);
            return redirect()->route('driver.packages.index')
                ->with('error', $checkout['error'] ?: 'Ödeme sayfası açılamadı. Lütfen tekrar deneyin.');
        }

        $package->update([
            'payment_reference' => $checkout['merchant_oid'],
            'conversation_id'   => $checkout['token'],   // PayTR token, idempotency için
            'payment_meta'      => $checkout['raw'] ?? [],
        ]);

        // Mock provider sürücüyü kendi sayfasına yönlendirir (redirect)
        if ($checkout['provider'] === 'mock') {
            return redirect()->away($checkout['iframe_url']);
        }

        // PayTR: iframe view
        return view('driver.packages_iframe', [
            'package'   => $package,
            'iframeUrl' => $checkout['iframe_url'],
        ]);
    }

    /**
     * POST /api/paytr/bildirim — PayTR sunucu-sunucu bildirim endpoint'i.
     * GÜVENLİK: Bu route CSRF muaftır + auth gerektirmez (bootstrap/app.php'de tanımlı).
     * Hash doğrulamayı PayTR kendi salt+key ile yapar, kimliği güvenilir.
     *
     * Cevap olarak SADECE "OK" string'i döndürmek ZORUNLU; aksi halde PayTR
     * bildirimi başarısız sayar ve tekrar dener.
     */
    public function paytrNotification(Request $request): Response
    {
        $post = $request->all();
        $merchantOid = $post['merchant_oid'] ?? null;

        // Diagnostic: hangi POST geldi, hangi durumda, sebep ne
        Log::info('PayTR bildirim alındı', [
            'merchant_oid' => $merchantOid,
            'status'       => $post['status'] ?? null,
            'total_amount' => $post['total_amount'] ?? null,
            'payment_type' => $post['payment_type'] ?? null,
            'ip'           => $request->ip(),
        ]);

        $gateway = GatewayFactory::make();
        $result = $gateway->verifyNotification($post);

        $package = $merchantOid
            ? DriverPackage::where('payment_reference', $merchantOid)->first()
            : null;

        if (! $package) {
            Log::warning('PayTR bildirim: paket bulunamadı', [
                'merchant_oid' => $merchantOid,
                'verify_ok'    => $result->success,
            ]);
            return response('OK')->header('Content-Type', 'text/plain');
        }

        // Idempotent: paket zaten işlenmişse tekrar onaylamayalım
        if (in_array($package->status, ['active', 'failed'], true)) {
            Log::info('PayTR bildirim: paket zaten işlenmiş', [
                'merchant_oid' => $merchantOid,
                'status'       => $package->status,
            ]);
            return response('OK')->header('Content-Type', 'text/plain');
        }

        if ($result->success) {
            $this->packages->markPaidAndActivate($package, $result->reference ?? $merchantOid, $result->raw);
            Log::info('PayTR bildirim: paket aktive edildi', [
                'merchant_oid' => $merchantOid,
                'package_id'   => $package->id,
            ]);
        } else {
            $this->packages->markFailed($package, $result->errorMessage ?? 'unknown', $result->raw);
            Log::warning('PayTR bildirim: ödeme başarısız', [
                'merchant_oid' => $merchantOid,
                'error'        => $result->errorMessage,
            ]);
        }

        return response('OK')->header('Content-Type', 'text/plain');
    }

    /**
     * GET /surucu-paneli/paketler/basarili — PayTR ödeme sonrası UX yönlendirme.
     * Sürücüye sadece "İşleniyor" mesajı gösterir; gerçek aktivasyon bildirimde olur.
     */
    public function success(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        return view('driver.packages_result', [
            'success'      => true,
            'title'        => 'Ödeme Alındı',
            'message'      => 'Ödemen başarılı. Paketin birkaç saniye içinde aktive olacak.',
            'redirectIn'   => 3,
        ]);
    }

    /**
     * GET /surucu-paneli/paketler/{package}/basarisiz
     */
    public function failure(DriverPackage $package): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');
        if ($package->driver_id !== $driver->id) abort(403);

        return view('driver.packages_result', [
            'success'    => false,
            'title'      => 'Ödeme Tamamlanmadı',
            'message'    => 'Ödeme sürecinde sorun çıktı. Tekrar deneyebilirsin.',
            'redirectIn' => 4,
        ]);
    }

    /**
     * GET /surucu-paneli/paketler/{package}/durum — sürücü iframe sayfasında
     * paket durumunu polling ile öğrenir (PayTR bildirim geldikten sonra).
     */
    public function status(DriverPackage $package): \Illuminate\Http\JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);
        if ($package->driver_id !== $driver->id) abort(403);

        return response()->json([
            'ok'         => true,
            'status'     => $package->status,
            'expires_at' => $package->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /surucu-paneli/paketler/{package}/mock — mock checkout (PAYTR_ENABLED=false).
     */
    public function mockCheckout(Request $request, DriverPackage $package): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');
        if ($package->driver_id !== $driver->id) abort(403);

        return view('driver.packages_mock_checkout', [
            'package'  => $package,
            'token'    => $request->query('token', 'MOCK'),
            'callback' => route('paytr.notification'),
            'merchantOid' => $package->payment_reference,
        ]);
    }
}
