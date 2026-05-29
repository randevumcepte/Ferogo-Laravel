<?php

namespace App\Modules\Booking\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Voice Telekom SMS API client (modern Laravel HTTP facade).
 *
 * Eski projedeki SDK'nın create-otp endpoint'inin birebir aynısını çağırır:
 *   POST {host}:{port}/sms/create-otp
 *   Auth: Basic base64(username:password)
 *   Body: { number, sender, encoding, content, validity, commercial }
 *
 * .env:
 *   VOICETELEKOM_ENABLED=true
 *   VOICETELEKOM_USERNAME=...
 *   VOICETELEKOM_PASSWORD=...
 *   VOICETELEKOM_SENDER=FEROGO          (gönderici başlığı — Voice Telekom panelinde onaylı)
 *   VOICETELEKOM_HOST=smsvt.voicetelekom.com
 *   VOICETELEKOM_PORT=9587               (9588 = HTTPS)
 *   VOICETELEKOM_OTP_VALIDITY=3          (dakika)
 *   VOICETELEKOM_COMMERCIAL=false
 */
class VoiceTelekomClient
{
    /**
     * Tek bir telefona OTP içerikli SMS yollar.
     *
     * NOT: Voice Telekom'un OTP-spesifik endpoint'i (sms/create-otp) ayrı bir
     * sender onay listesi tutuyor; eski Randevumcepte projesindeki kullanılan ve
     * sender'ı onaylı endpoint sms/create (sendSingleSms). Bu yüzden OTP
     * gönderimini de aynı endpoint üstünden yapıyoruz — content içerik OTP
     * olduğu sürece düzgün iletilir.
     *
     * @return array{ok: bool, pkg_id?: string, message?: string}
     */
    public function sendOtp(string $phone, string $content): array
    {
        $cfg = config('services.voicetelekom');

        if (! ($cfg['enabled'] ?? false)) {
            return ['ok' => false, 'message' => 'SMS provider disabled (VOICETELEKOM_ENABLED=false).'];
        }
        if (empty($cfg['username']) || empty($cfg['password']) || empty($cfg['sender'])) {
            return ['ok' => false, 'message' => 'SMS provider credentials missing.'];
        }

        $port   = (int) ($cfg['port'] ?? 9587);
        $scheme = $port === 9588 ? 'https' : 'http';
        $url    = sprintf('%s://%s:%d/sms/create', $scheme, $cfg['host'], $port);

        // sendSingleSms (eski SDK toString) payload yapısı — sender onayı bu endpoint'e bağlı.
        $payload = [
            'type'         => 1,
            'sendingType'  => 0,
            'title'        => 'Dogrulama',
            'encoding'     => 0, // 0 = GSM7 (OTP latin metni için yeterli)
            'content'      => $content,
            'number'       => $this->formatNumber($phone),
            'sender'       => $cfg['sender'],
            'periodicSettings' => null,
            'sendingDate'  => null,
            'validity'     => max(60, (int) ($cfg['validity'] ?? 60)),
            'commercial'   => (bool) ($cfg['commercial'] ?? false),
            'skipAhsQuery' => true, // OTP/bilgilendirme — AHS sorgusu atlanır
            'customID'     => 'ferogo_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8),
        ];

        try {
            $response = Http::withBasicAuth($cfg['username'], $cfg['password'])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->withOptions([
                    'verify' => false, // VT 9588 SSL self-signed olabiliyor — eski SDK da kapatıyordu
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('[VoiceTelekom] HTTP exception: ' . $e->getMessage(), [
                'phone' => $this->maskNumber($phone),
            ]);
            return ['ok' => false, 'message' => 'SMS gönderim hatası: ' . $e->getMessage()];
        }

        $body = $response->json() ?? [];

        if (isset($body['err']) && $body['err'] !== null) {
            $code = $body['err']['code']    ?? 'unknown';
            $msg  = $body['err']['message'] ?? 'unknown error';
            Log::warning('[VoiceTelekom] API error', [
                'phone'     => $this->maskNumber($phone),
                'http_code' => $response->status(),
                'err_code'  => $code,
                'err_msg'   => $msg,
            ]);
            return ['ok' => false, 'message' => "VT hata: {$code} — {$msg}"];
        }

        $pkgId = $body['data']['pkgID'] ?? null;
        Log::info('[VoiceTelekom] SMS sent', [
            'phone'  => $this->maskNumber($phone),
            'pkg_id' => $pkgId,
        ]);

        return ['ok' => true, 'pkg_id' => (string) $pkgId];
    }

    /**
     * Bu credential'a tanımlı onaylı gönderici başlıklarını listeler.
     * ERR_INVALID_SMS_SENDER hatası alındığında debug için.
     *
     * @return array{ok: bool, senders?: array<int, mixed>, message?: string, raw?: array}
     */
    public function listSenders(): array
    {
        $cfg = config('services.voicetelekom');

        if (empty($cfg['username']) || empty($cfg['password'])) {
            return ['ok' => false, 'message' => 'Credentials missing.'];
        }

        $port   = (int) ($cfg['port'] ?? 9587);
        $scheme = $port === 9588 ? 'https' : 'http';
        $url    = sprintf('%s://%s:%d/sms/list-sender', $scheme, $cfg['host'], $port);

        try {
            $response = Http::withBasicAuth($cfg['username'], $cfg['password'])
                ->acceptJson()
                ->timeout(15)
                ->withOptions(['verify' => false])
                ->post($url, []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'HTTP exception: ' . $e->getMessage()];
        }

        $body = $response->json() ?? [];

        if (isset($body['err']) && $body['err'] !== null) {
            return [
                'ok'      => false,
                'message' => 'VT err: ' . ($body['err']['code'] ?? '?') . ' — ' . ($body['err']['message'] ?? '?'),
                'raw'     => $body,
            ];
        }

        // VT response yapısı: { data: { senders: [...] } } veya { data: [...] }
        $senders = $body['data']['senders']
            ?? $body['data']['list']
            ?? $body['data']
            ?? [];

        return [
            'ok'      => true,
            'senders' => is_array($senders) ? $senders : [],
            'raw'     => $body,
        ];
    }

    /**
     * VT API "905XX..." veya "5XX..." kabul ediyor. Her ihtimale karşı 90 prefix'i ile gönderelim.
     */
    protected function formatNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '90' . $digits;
        }
        return $digits;
    }

    protected function maskNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 6) return '***';
        return substr($digits, 0, 4) . '***' . substr($digits, -3);
    }
}
