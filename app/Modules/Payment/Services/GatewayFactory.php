<?php

namespace App\Modules\Payment\Services;

/**
 * services.iyzico.enabled bayrağına göre doğru gateway'i döner.
 * Tek noktadan provider seçimi — controller bu detayı bilmek zorunda değil.
 */
final class GatewayFactory
{
    public static function make(): PaymentGateway
    {
        $cfg = config('services.iyzico', []);

        if (! empty($cfg['enabled']) && ! empty($cfg['api_key']) && ! empty($cfg['secret_key'])) {
            return new IyzicoGateway(
                apiKey:    $cfg['api_key'],
                secretKey: $cfg['secret_key'],
                baseUrl:   $cfg['base_url'] ?? 'https://sandbox-api.iyzipay.com',
            );
        }

        return new MockGateway();
    }
}
