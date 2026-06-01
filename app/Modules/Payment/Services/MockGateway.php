<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;

/**
 * Dev için. Gerçek tahsilat YAPMAZ.
 * Mock = sürücü "fake checkout" sayfasına yönlenir, "Ödedim" diye onay verince
 * paket aktive olur. PayTR credential yokken çalışır.
 */
final class MockGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function initCheckout(DriverPackage $package): array
    {
        $token = 'MOCK-' . $package->id . '-' . substr(md5((string) microtime(true)), 0, 10);
        $merchantOid = 'MOCK' . date('YmdHis') . $package->id;

        // Sürücüyü kendi mock sayfamıza yönlendiriyoruz.
        $iframeUrl = route('driver.packages.mock_checkout', ['package' => $package->id, 'token' => $token]);

        return [
            'iframe_url'   => $iframeUrl,
            'token'        => $token,
            'merchant_oid' => $merchantOid,
            'provider'     => 'mock',
            'error'        => null,
            'raw'          => ['note' => 'mock provider — PAYTR_ENABLED=false'],
        ];
    }

    public function verifyNotification(array $post): PaymentResult
    {
        return PaymentResult::ok(
            $post['merchant_oid'] ?? ('MOCK-' . time()),
            $post,
        );
    }
}
