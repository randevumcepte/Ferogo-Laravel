<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;

/**
 * Dev/staging için. Gerçek tahsilat YAPMAZ.
 * Mock'ta saklı kart yok — saklı kart akışı sadece IYZICO_ENABLED=true iken anlamlı.
 * Mock checkout = fake ödeme sayfası, "Ödedim" butonuna basınca paket aktive.
 */
final class MockGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function initCheckout(DriverPackage $package, string $callbackUrl, ?string $cardUserKey = null): array
    {
        $token = 'MOCK-' . $package->id . '-' . substr(md5((string) microtime(true)), 0, 10);

        $redirect = route('driver.packages.mock_checkout', ['package' => $package->id, 'token' => $token]);

        return [
            'redirect_url'    => $redirect,
            'token'           => $token,
            'provider'        => 'mock',
            'conversation_id' => 'mock_' . $package->id . '_' . time(),
            'raw'             => ['note' => 'mock provider — IYZICO_ENABLED=false'],
        ];
    }

    public function verifyCallback(array $payload): PaymentResult
    {
        $ref = $payload['token'] ?? ('MOCK-OK-' . time());
        return PaymentResult::ok($ref, $payload);
    }

    public function listSavedCards(string $cardUserKey): array
    {
        return [];
    }

    public function deleteSavedCard(string $cardUserKey, string $cardToken): bool
    {
        return true;
    }

    public function init3dPayment(DriverPackage $package, string $cardUserKey, string $cardToken, string $callbackUrl): array
    {
        // Mock'ta 3D yok — direkt mock checkout'a yönlendir
        return [
            'success'         => false,
            'html_content'    => null,
            'conversation_id' => 'mock3d_' . $package->id,
            'error'           => 'Mock provider 3D ödeme desteklemiyor (IYZICO_ENABLED=false).',
            'raw'             => [],
        ];
    }

    public function complete3dPayment(array $payload): PaymentResult
    {
        return PaymentResult::ok($payload['token'] ?? 'MOCK-3DS-OK', $payload);
    }
}
