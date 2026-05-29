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
        $url    = sprintf('%s://%s:%d/sms/create-otp', $scheme, $cfg['host'], $port);

        $payload = [
            'number'     => $this->formatNumber($phone),
            'sender'     => $cfg['sender'],
            'encoding'   => 0, // 0 = GSM7 (Türkçe karakterler düşer; OTP latin metni için yeterli)
            'content'    => $content,
            'validity'   => (int) ($cfg['validity'] ?? 3),
            'commercial' => (bool) ($cfg['commercial'] ?? false),
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
