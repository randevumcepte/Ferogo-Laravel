<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\DriverPackage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayTR iFrame API entegrasyonu.
 *
 * Akış:
 *   1. initCheckout() → POST /odeme/api/get-token → token al
 *      Sürücüye iframe URL'ini gösteririz (https://www.paytr.com/odeme/guvenli/{token})
 *      Bu iframe içinde kart girişi, 3D Secure, saklı kart ve Masterpass otomatik yönetilir.
 *   2. Sürücü iframe'de ödeme yapar; PayTR sunucudan sunucuya bizim bildirim URL'imize POST eder.
 *   3. verifyNotification() → hash doğrulama + status="success" → paket aktive.
 *      ÖNEMLİ: Frontend success/fail page'ler sadece UX'tir; gerçek onay bildirimden gelir.
 *
 * Doc: https://dev.paytr.com/iframe-api
 */
final class PayTRGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $merchantKey,
        private readonly string $merchantSalt,
        private readonly bool $testMode = true,
        private readonly int $timeoutLimit = 30,   // dakika
        private readonly int $maxInstallment = 1,  // 1 = tek çekim (komisyon yok / paket aboneliği)
    ) {}

    public function name(): string
    {
        return 'paytr';
    }

    /**
     * Token al ve iframe URL'i döndür.
     */
    public function initCheckout(DriverPackage $package): array
    {
        $driver = $package->driver()->with('user')->first();
        $user   = $driver?->user;

        // PayTR merchant_oid sadece harf+rakam, benzersiz; biz zamana paket id ekliyoruz.
        $merchantOid = 'FERO' . date('YmdHis') . str_pad((string) $package->id, 6, '0', STR_PAD_LEFT);

        // PayTR kuruş (integer) bekler — 199.00 ₺ → 19900
        $paymentAmount = (int) round((float) $package->price * 100);

        // Sepet: [[ürün adı, fiyat_kurus, adet], ...]
        $packageLabel = 'FerXGo ' . ($package->definition()['label'] ?? $package->type) . ' Sürücü Paketi';
        $basket = [[
            $packageLabel,
            (string) $paymentAmount,
            1,
        ]];
        $userBasket = base64_encode(json_encode($basket, JSON_UNESCAPED_UNICODE));

        $email = $user?->email ?: 'no-reply@ferogo.app';
        $userName = $user?->name ?: 'Sürücü';
        $userAddress = 'FerXGo Sürücü Paneli';
        $userPhone = $user?->phone ?: '+905555555555';
        $userIp = request()->ip() ?: '85.34.78.112';
        $testMode = $this->testMode ? 1 : 0;
        $noInstallment = 1;             // taksit yok, sadece tek çekim
        $maxInstallment = $this->maxInstallment;
        $currency = 'TL';

        $merchantOkUrl   = route('driver.packages.success');
        $merchantFailUrl = route('driver.packages.failure', ['package' => $package->id]);

        // PayTR token hash formülü (sıra önemli, dokümandaki sırayı koru)
        $hashStr = $this->merchantId
            . $userIp
            . $merchantOid
            . $email
            . $paymentAmount
            . $userBasket
            . $noInstallment
            . $maxInstallment
            . $currency
            . $testMode;

        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . $this->merchantSalt, $this->merchantKey, true));

        $postVals = [
            'merchant_id'       => $this->merchantId,
            'user_ip'           => $userIp,
            'merchant_oid'      => $merchantOid,
            'email'             => $email,
            'payment_amount'    => $paymentAmount,
            'paytr_token'       => $paytrToken,
            'user_basket'       => $userBasket,
            'debug_on'          => $this->testMode ? 1 : 0,
            'no_installment'    => $noInstallment,
            'max_installment'   => $maxInstallment,
            'user_name'         => $userName,
            'user_address'      => $userAddress,
            'user_phone'        => $userPhone,
            'merchant_ok_url'   => $merchantOkUrl,
            'merchant_fail_url' => $merchantFailUrl,
            'timeout_limit'     => $this->timeoutLimit,
            'currency'          => $currency,
            'test_mode'         => $testMode,
        ];

        try {
            $resp = Http::asForm()
                ->timeout(20)
                ->post('https://www.paytr.com/odeme/api/get-token', $postVals);

            $result = $resp->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('PayTR get-token connection error', ['err' => $e->getMessage()]);
            return [
                'iframe_url'   => null,
                'token'        => null,
                'merchant_oid' => $merchantOid,
                'provider'     => 'paytr',
                'error'        => 'Ödeme sunucusuna bağlanılamadı.',
                'raw'          => [],
            ];
        }

        if (($result['status'] ?? null) !== 'success') {
            Log::warning('PayTR get-token failed', ['result' => $result]);
            return [
                'iframe_url'   => null,
                'token'        => null,
                'merchant_oid' => $merchantOid,
                'provider'     => 'paytr',
                'error'        => $result['reason'] ?? 'Ödeme başlatılamadı.',
                'raw'          => $result,
            ];
        }

        return [
            'iframe_url'   => 'https://www.paytr.com/odeme/guvenli/' . $result['token'],
            'token'        => $result['token'],
            'merchant_oid' => $merchantOid,
            'provider'     => 'paytr',
            'error'        => null,
            'raw'          => $result,
        ];
    }

    /**
     * PayTR bildirim doğrulama.
     * Hash formülü: hash_hmac(sha256, merchant_oid + salt + status + total_amount, merchant_key)
     * Bu hash POST içindeki "hash" alanı ile birebir aynı olmalı; aksi takdirde reddet.
     */
    public function verifyNotification(array $post): PaymentResult
    {
        $merchantOid = $post['merchant_oid'] ?? null;
        $status      = $post['status'] ?? null;
        $totalAmount = $post['total_amount'] ?? null;
        $providedHash = $post['hash'] ?? null;

        if (! $merchantOid || ! $status || ! $totalAmount || ! $providedHash) {
            return PaymentResult::fail('Eksik bildirim parametreleri', $post);
        }

        $computedHash = base64_encode(hash_hmac(
            'sha256',
            $merchantOid . $this->merchantSalt . $status . $totalAmount,
            $this->merchantKey,
            true,
        ));

        if (! hash_equals($computedHash, $providedHash)) {
            Log::warning('PayTR hash mismatch', [
                'merchant_oid' => $merchantOid,
                'provided'     => $providedHash,
                'computed'     => $computedHash,
            ]);
            return PaymentResult::fail('Geçersiz hash', $post);
        }

        if ($status !== 'success') {
            return PaymentResult::fail(
                $post['failed_reason_msg'] ?? ('PayTR durum: ' . $status),
                $post,
            );
        }

        return PaymentResult::ok($merchantOid, $post);
    }
}
