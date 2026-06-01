<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iyzico entegrasyonu — Checkout Form + Card Storage + 3D Secure + Masterpass.
 *
 * Masterpass: buyer.gsmNumber göndererek otomatik aktif olur — iyzico checkout
 * sayfasında "Masterpass ile öde" butonu çıkar, kullanıcı telefon ile login olup
 * Masterpass'teki kartlarını seçebilir.
 *
 * Card Storage: cardUserKey ile kullanıcının kartları iyzico'da saklanır.
 *   - İlk ödeme: cardUserKey YOK → Checkout Form'da "Bu kartı kaydet" işaretle →
 *     callback'te iyzico cardUserKey üretir, biz user'a yazarız.
 *   - Sonraki ödeme: cardUserKey VAR → init3dPayment ile tek tıkla 3D ödeme.
 *
 * SDK kullanmıyoruz — minimum HTTP + HMAC. Endpoint'ler tek tip (paket satın alma)
 * olduğu için iyzico-php SDK ağırlığı taşımaya değmez.
 *
 * Doc: https://docs.iyzico.com/
 */
final class IyzicoGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    public function name(): string
    {
        return 'iyzico';
    }

    // ─────────────────────────────────────────────────────────
    // 1. CHECKOUT FORM (yeni kart + Masterpass + saklı kart listesi)
    // ─────────────────────────────────────────────────────────

    public function initCheckout(DriverPackage $package, string $callbackUrl, ?string $cardUserKey = null): array
    {
        $driver = $package->driver()->with('user')->first();
        $user   = $driver?->user;

        $conversationId = 'pkg_' . $package->id . '_' . time();
        $price = number_format((float) $package->price, 2, '.', '');

        $body = [
            'locale'         => 'tr',
            'conversationId' => $conversationId,
            'price'          => $price,
            'paidPrice'      => $price,
            'currency'       => 'TRY',
            'basketId'       => 'PKG-' . $package->id,
            'paymentGroup'   => 'SUBSCRIPTION',
            'callbackUrl'    => $callbackUrl,
            'enabledInstallments' => [1],
            'buyer'          => $this->buyerPayload($driver, $user),
            'shippingAddress' => $this->addressPayload($user),
            'billingAddress'  => $this->addressPayload($user),
            'basketItems'    => [[
                'id'        => 'PKG-' . $package->type,
                'name'      => 'Ferogo ' . ($package->definition()['label'] ?? $package->type) . ' Paket',
                'category1' => 'Subscription',
                'itemType'  => 'VIRTUAL',
                'price'     => $price,
            ]],
        ];

        // Saklı kart desteği — varsa kullanıcının kartları sayfada otomatik listelenir
        if ($cardUserKey) {
            $body['cardUserKey'] = $cardUserKey;
        }

        $response = $this->request('POST', '/payment/iyzipos/checkoutform/initialize/auth/ecom', $body);

        if (($response['status'] ?? null) !== 'success') {
            Log::warning('iyzico initCheckout failed', ['response' => $response]);
            return [
                'redirect_url'    => null,
                'token'           => null,
                'provider'        => 'iyzico',
                'conversation_id' => $conversationId,
                'raw'             => $response,
            ];
        }

        return [
            'redirect_url'    => $response['paymentPageUrl'] ?? null,
            'token'           => $response['token'] ?? null,
            'provider'        => 'iyzico',
            'conversation_id' => $conversationId,
            'raw'             => $response,
        ];
    }

    public function verifyCallback(array $payload): PaymentResult
    {
        $token = $payload['token'] ?? null;
        if (! $token) {
            return PaymentResult::fail('iyzico token yok', $payload);
        }

        $body = [
            'locale'         => 'tr',
            'conversationId' => $payload['conversationId'] ?? ('ver_' . time()),
            'token'          => $token,
        ];

        $response = $this->request('POST', '/payment/iyzipos/checkoutform/auth/ecom/detail', $body);

        $apiOk     = ($response['status'] ?? null) === 'success';
        $paymentOk = ($response['paymentStatus'] ?? null) === 'SUCCESS';

        if ($apiOk && $paymentOk) {
            return PaymentResult::ok((string) ($response['paymentId'] ?? $token), $response);
        }

        return PaymentResult::fail(
            $response['errorMessage'] ?? 'iyzico ödeme başarısız',
            $response,
        );
    }

    // ─────────────────────────────────────────────────────────
    // 2. CARD STORAGE (saklı kart listele / sil)
    // ─────────────────────────────────────────────────────────

    public function listSavedCards(string $cardUserKey): array
    {
        $body = [
            'locale'         => 'tr',
            'conversationId' => 'cards_' . time(),
            'cardUserKey'    => $cardUserKey,
        ];

        $response = $this->request('POST', '/cardstorage/cards', $body);

        if (($response['status'] ?? null) !== 'success') {
            // 1206 = userKey bulunamadı (henüz kart yok) — bu hata değil
            if (($response['errorCode'] ?? null) !== '1206') {
                Log::warning('iyzico listSavedCards failed', ['response' => $response]);
            }
            return [];
        }

        $rows = $response['cardDetails'] ?? [];
        return array_map(fn ($r) => SavedCard::fromIyzico($r), $rows);
    }

    public function deleteSavedCard(string $cardUserKey, string $cardToken): bool
    {
        $body = [
            'locale'         => 'tr',
            'conversationId' => 'delcard_' . time(),
            'cardUserKey'    => $cardUserKey,
            'cardToken'      => $cardToken,
        ];

        $response = $this->request('DELETE', '/cardstorage/card', $body);
        return ($response['status'] ?? null) === 'success';
    }

    // ─────────────────────────────────────────────────────────
    // 3. 3D SECURE (saklı kart ile tek tıkla ödeme)
    // ─────────────────────────────────────────────────────────

    public function init3dPayment(DriverPackage $package, string $cardUserKey, string $cardToken, string $callbackUrl): array
    {
        $driver = $package->driver()->with('user')->first();
        $user   = $driver?->user;

        $conversationId = 'pkg3d_' . $package->id . '_' . time();
        $price = number_format((float) $package->price, 2, '.', '');

        $body = [
            'locale'         => 'tr',
            'conversationId' => $conversationId,
            'price'          => $price,
            'paidPrice'      => $price,
            'currency'       => 'TRY',
            'installment'    => 1,
            'basketId'       => 'PKG-' . $package->id,
            'paymentChannel' => 'WEB',
            'paymentGroup'   => 'SUBSCRIPTION',
            'callbackUrl'    => $callbackUrl,
            'paymentCard'    => [
                'cardUserKey' => $cardUserKey,
                'cardToken'   => $cardToken,
            ],
            'buyer'           => $this->buyerPayload($driver, $user),
            'shippingAddress' => $this->addressPayload($user),
            'billingAddress'  => $this->addressPayload($user),
            'basketItems'     => [[
                'id'        => 'PKG-' . $package->type,
                'name'      => 'Ferogo ' . ($package->definition()['label'] ?? $package->type) . ' Paket',
                'category1' => 'Subscription',
                'itemType'  => 'VIRTUAL',
                'price'     => $price,
            ]],
        ];

        $response = $this->request('POST', '/payment/3dsecure/initialize', $body);

        $ok = ($response['status'] ?? null) === 'success';
        if (! $ok) {
            Log::warning('iyzico init3dPayment failed', ['response' => $response]);
            return [
                'success'         => false,
                'html_content'    => null,
                'conversation_id' => $conversationId,
                'error'           => $response['errorMessage'] ?? '3D başlatılamadı',
                'raw'             => $response,
            ];
        }

        // htmlContent base64 encoded geliyor; biz decode edip iframe içinde render edeceğiz.
        $html = $response['threeDSHtmlContent'] ?? null;
        if ($html) $html = base64_decode($html);

        return [
            'success'         => true,
            'html_content'    => $html,
            'conversation_id' => $conversationId,
            'error'           => null,
            'raw'             => $response,
        ];
    }

    public function complete3dPayment(array $payload): PaymentResult
    {
        // iyzico 3D bittiğinde callback URL'e POST eder:
        //   status, paymentId, conversationData, conversationId, mdStatus
        // mdStatus=1 → başarılı, diğer kodlar fail. /payment/3dsecure/auth ile finalize.
        $paymentId        = $payload['paymentId'] ?? null;
        $conversationData = $payload['conversationData'] ?? null;
        $conversationId   = $payload['conversationId'] ?? ('auth3d_' . time());
        $mdStatus         = $payload['mdStatus'] ?? null;

        if (! $paymentId) {
            return PaymentResult::fail('3D paymentId yok', $payload);
        }

        if ((string) $mdStatus !== '1') {
            return PaymentResult::fail(
                '3D doğrulama başarısız (mdStatus=' . $mdStatus . ')',
                $payload,
            );
        }

        $body = [
            'locale'           => 'tr',
            'conversationId'   => $conversationId,
            'paymentId'        => $paymentId,
            'conversationData' => $conversationData,
        ];

        $response = $this->request('POST', '/payment/3dsecure/auth', $body);

        $apiOk     = ($response['status'] ?? null) === 'success';
        $paymentOk = ($response['paymentStatus'] ?? null) === 'SUCCESS';

        if ($apiOk && $paymentOk) {
            return PaymentResult::ok((string) ($response['paymentId'] ?? $paymentId), $response);
        }

        return PaymentResult::fail(
            $response['errorMessage'] ?? '3D auth başarısız',
            $response,
        );
    }

    // ─────────────────────────────────────────────────────────
    // YARDIMCI
    // ─────────────────────────────────────────────────────────

    private function buyerPayload($driver, $user): array
    {
        return [
            'id'                  => 'driver-' . ($driver?->id ?? 0),
            'name'                => $user?->name ?: 'Surucu',
            'surname'             => 'Ferogo',
            // Masterpass için kritik — bu numara ile Masterpass'e bağlanır
            'gsmNumber'           => $this->normalizePhone($user?->phone),
            'email'               => $user?->email ?: 'no-reply@ferogo.app',
            'identityNumber'      => $user?->tc_no ?: '11111111111',
            'registrationAddress' => 'Ferogo Surucu Paneli',
            'ip'                  => request()->ip() ?: '85.34.78.112',
            'city'                => 'Izmir',
            'country'             => 'Turkey',
        ];
    }

    private function addressPayload($user): array
    {
        return [
            'contactName' => $user?->name ?: 'Surucu',
            'city'        => 'Izmir',
            'country'     => 'Turkey',
            'address'     => 'Ferogo Surucu Paneli',
        ];
    }

    /**
     * iyzico +905XXXXXXXXX bekler. Veritabanında 5XX/+90/0... gibi farklı format olabilir.
     */
    private function normalizePhone(?string $phone): string
    {
        if (! $phone) return '+905555555555';
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '90')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+9' . $digits;
        }
        if (strlen($digits) === 10) {
            return '+90' . $digits;
        }
        return '+905555555555';
    }

    /**
     * iyzico v2 HMAC-SHA256 auth.
     */
    private function request(string $method, string $uri, array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        $randomKey = (string) (now()->timestamp . random_int(1000, 9999));
        $signature = hash_hmac(
            'sha256',
            $randomKey . $payload,
            $this->secretKey,
        );

        $authString = 'apiKey:' . $this->apiKey
            . '&randomKey:' . $randomKey
            . '&signature:' . $signature;

        $auth = 'IYZWSv2 ' . base64_encode($authString);

        $resp = Http::withHeaders([
            'Authorization' => $auth,
            'x-iyzi-rnd'    => $randomKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])
            ->withBody($payload, 'application/json')
            ->timeout(20)
            ->send($method, rtrim($this->baseUrl, '/') . $uri);

        if (! $resp->ok()) {
            Log::warning('iyzico HTTP non-2xx', [
                'uri' => $uri, 'status' => $resp->status(), 'body' => $resp->body(),
            ]);
            return ['status' => 'failure', 'errorMessage' => 'HTTP ' . $resp->status()];
        }

        return $resp->json() ?? ['status' => 'failure', 'errorMessage' => 'invalid json'];
    }
}
