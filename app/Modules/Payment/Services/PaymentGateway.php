<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;

/**
 * Tüm payment gateway'lerin uyduğu sözleşme.
 * iyzico Card Storage + 3D Secure + Masterpass'i kapsar.
 */
interface PaymentGateway
{
    /**
     * Yeni kart ile ödeme — iyzico Checkout Form (Masterpass otomatik aktif).
     * cardUserKey verirsen önceki kayıtlı kartlar listelenir + yeni kart eklenebilir.
     *
     * @return array{
     *   redirect_url: string|null,
     *   token: string|null,
     *   provider: string,
     *   conversation_id: string|null,
     *   raw: array,
     * }
     */
    public function initCheckout(DriverPackage $package, string $callbackUrl, ?string $cardUserKey = null): array;

    /**
     * Checkout Form callback'i — kart bilgisi de döndürür (saklı kart için).
     */
    public function verifyCallback(array $payload): PaymentResult;

    /**
     * Kullanıcının saklı kartlarını iyzico'dan getir.
     *
     * @return SavedCard[]
     */
    public function listSavedCards(string $cardUserKey): array;

    /**
     * Saklı kartı sil.
     */
    public function deleteSavedCard(string $cardUserKey, string $cardToken): bool;

    /**
     * Saklı kart ile 3D Secure ödeme başlat — htmlContent döner.
     * Sürücüye HTML render edilir, 3D bittiğinde callback URL'e POST gelir.
     *
     * @return array{
     *   success: bool,
     *   html_content: string|null,
     *   conversation_id: string|null,
     *   error: string|null,
     *   raw: array,
     * }
     */
    public function init3dPayment(DriverPackage $package, string $cardUserKey, string $cardToken, string $callbackUrl): array;

    /**
     * 3D doğrulamadan dönen POST'u finalize et — paymentId + conversationId ile auth.
     */
    public function complete3dPayment(array $payload): PaymentResult;

    public function name(): string;
}
