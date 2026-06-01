<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;

/**
 * Tüm payment gateway'lerin uyduğu sözleşme.
 * PayTR iframe akışına göre tasarlandı — saklı kart, Masterpass, 3D Secure
 * iframe içinde otomatik yönetiliyor; bizim için sadece 2 endpoint var:
 *   1. Token al → iframe URL
 *   2. Bildirim doğrula → paket aktive
 */
interface PaymentGateway
{
    /**
     * Ödeme tokeni al. Sürücüye iframe içinde gösterilecek URL döner.
     *
     * @return array{
     *   iframe_url: string|null,
     *   token: string|null,
     *   merchant_oid: string,
     *   provider: string,
     *   error: string|null,
     *   raw: array,
     * }
     */
    public function initCheckout(DriverPackage $package): array;

    /**
     * Gateway'in bildirim (notification) endpoint'ine yaptığı POST'u doğrula.
     * Hash kontrol + status="success" → ok.
     */
    public function verifyNotification(array $post): PaymentResult;

    public function name(): string;
}
