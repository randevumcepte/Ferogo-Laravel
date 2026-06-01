<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iyzico Checkout Form API entegrasyonu.
 *
 * Akış:
 *   1. initCheckout()  → /payment/iyzipos/checkoutform/initialize/auth/ecom
 *      Dönüş: paymentPageUrl + token. Sürücü iyzico sayfasına yönlenir.
 *   2. iyzico ödeme bitince callbackUrl'e POST: { token: "..." }
 *   3. verifyCallback() → /payment/iyzipos/checkoutform/auth/ecom/detail
 *      Dönüş: paymentStatus=SUCCESS ise paket aktive edilir.
 *
 * Doc: https://docs.iyzico.com/api/checkout-form
 *
 * NOT: iyzico SDK yerine doğrudan HTTP kullanıyoruz — paket satın alma
 * tek tip ürün (paket), karmaşık taksit / iade akışı yok. Bu yüzden
 * minimal HMAC-SHA256 auth ile yetiyor; SDK paket bağımlılığı eklemiyoruz.
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

    public function initCheckout(DriverPackage $package, string $callbackUrl): array
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
            'buyer' => [
                'id'                  => 'driver-' . $driver?->id,
                'name'                => $user?->name ?: 'Surucu',
                'surname'             => 'Ferogo',
                'gsmNumber'           => $user?->phone ?: '+905555555555',
                'email'               => $user?->email ?: 'no-reply@ferogo.app',
                'identityNumber'      => '11111111111',
                'registrationAddress' => 'Ferogo Surucu Paneli',
                'ip'                  => request()->ip() ?: '85.34.78.112',
                'city'                => 'Izmir',
                'country'             => 'Turkey',
            ],
            'shippingAddress' => [
                'contactName' => $user?->name ?: 'Surucu',
                'city'        => 'Izmir',
                'country'     => 'Turkey',
                'address'     => 'Ferogo Surucu Paneli',
            ],
            'billingAddress' => [
                'contactName' => $user?->name ?: 'Surucu',
                'city'        => 'Izmir',
                'country'     => 'Turkey',
                'address'     => 'Ferogo Surucu Paneli',
            ],
            'basketItems' => [[
                'id'        => 'PKG-' . $package->type,
                'name'      => 'Ferogo ' . ($package->definition()['label'] ?? $package->type) . ' Paket',
                'category1' => 'Subscription',
                'itemType'  => 'VIRTUAL',
                'price'     => $price,
            ]],
        ];

        $uri = '/payment/iyzipos/checkoutform/initialize/auth/ecom';
        $response = $this->request('POST', $uri, $body);

        if (($response['status'] ?? null) !== 'success') {
            Log::warning('iyzico initCheckout failed', ['response' => $response]);
            return [
                'redirect_url' => null,
                'token'        => null,
                'provider'     => 'iyzico',
                'raw'          => $response,
            ];
        }

        return [
            'redirect_url' => $response['paymentPageUrl'] ?? null,
            'token'        => $response['token'] ?? null,
            'provider'     => 'iyzico',
            'raw'          => $response,
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

    /**
     * iyzico Authorization header: PKI string'in HMAC-SHA256'sı.
     * Yeni nesil "v2" auth daha kolay ama bazı endpoint'ler eski PKI ister.
     * Burada v2 HMAC-SHA256 kullanıyoruz.
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
