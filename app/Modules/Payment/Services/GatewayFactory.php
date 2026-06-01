<?php

namespace App\Modules\Payment\Services;

/**
 * services.paytr.enabled bayrağına göre PayTR veya Mock döner.
 */
final class GatewayFactory
{
    public static function make(): PaymentGateway
    {
        $cfg = config('services.paytr', []);

        if (! empty($cfg['enabled']) && ! empty($cfg['merchant_id']) && ! empty($cfg['merchant_key']) && ! empty($cfg['merchant_salt'])) {
            return new PayTRGateway(
                merchantId:    (string) $cfg['merchant_id'],
                merchantKey:   (string) $cfg['merchant_key'],
                merchantSalt:  (string) $cfg['merchant_salt'],
                testMode:      (bool) ($cfg['test_mode'] ?? true),
                timeoutLimit:  (int) ($cfg['timeout_limit'] ?? 30),
                maxInstallment:(int) ($cfg['max_installment'] ?? 1),
            );
        }

        return new MockGateway();
    }
}
