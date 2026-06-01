<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;

/**
 * Dev/staging için. Gerçek tahsilat YAPMAZ.
 * Checkout = bizim "fake ödeme" sayfamıza yönlendirir;
 * sürücü oradan "Ödedim" butonuna basınca paket aktive olur.
 * Üretime almadan önce IYZICO_ENABLED=true yapılmalı.
 */
final class MockGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function initCheckout(DriverPackage $package, string $callbackUrl): array
    {
        $token = 'MOCK-' . $package->id . '-' . substr(md5((string) microtime(true)), 0, 10);

        // Sürücüyü kendi "fake checkout" sayfamıza gönderiyoruz.
        $redirect = route('driver.packages.mock_checkout', ['package' => $package->id, 'token' => $token]);

        return [
            'redirect_url' => $redirect,
            'token'        => $token,
            'provider'     => 'mock',
            'raw'          => ['note' => 'mock provider — IYZICO_ENABLED=false'],
        ];
    }

    public function verifyCallback(array $payload): PaymentResult
    {
        // Mock akışında callback geldiyse → her zaman başarılı kabul edilir.
        $ref = $payload['token'] ?? ('MOCK-OK-' . time());
        return PaymentResult::ok($ref, $payload);
    }
}
