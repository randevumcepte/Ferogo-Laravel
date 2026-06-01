<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;

/**
 * Tüm payment gateway'lerin uyduğu sözleşme.
 * Şu an iki implementasyon var: IyzicoGateway (prod) ve MockGateway (dev).
 */
interface PaymentGateway
{
    /**
     * Sürücüyü ödeme sayfasına yönlendirmek için checkout başlatır.
     *
     * @return array{
     *   redirect_url: string|null,
     *   token: string|null,
     *   provider: string,
     *   raw: array,
     * }
     */
    public function initCheckout(DriverPackage $package, string $callbackUrl): array;

    /**
     * Callback'ten gelen veriyle ödemenin gerçekten başarılı olduğunu doğrular.
     */
    public function verifyCallback(array $payload): PaymentResult;

    public function name(): string;
}
