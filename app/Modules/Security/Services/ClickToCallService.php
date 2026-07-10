<?php

namespace App\Modules\Security\Services;

use Illuminate\Support\Facades\Log;

/**
 * Click-to-call — operatör panelinden tek tıkla santral üzerinden arama.
 *
 * Sürücü/Filo: FreePBX (Asterisk) AMI Originate ile.
 *   1) Operatörün dahilisi çalar (gelen çağrı gibi).
 *   2) Operatör açınca santral, alarmı gönderen kişinin numarasını arar ve köprüler.
 *   3) Arayan kimliği "FERXGO ACIL DURUM" görünür.
 *
 * Yapılandırma .env (services.panic.click_to_call):
 *   PANIC_CLICK_TO_CALL_ENABLED=true
 *   AMI_HOST=89.252.140.61
 *   AMI_PORT=5038
 *   AMI_USERNAME=...            (FreePBX: Settings → Asterisk Manager Users)
 *   AMI_SECRET=...
 *   PANIC_OPERATOR_CHANNEL=PJSIP/1001   (operatör dahili kanalı; SIP/1001 de olabilir)
 *   PANIC_CALL_CONTEXT=from-internal
 *   PANIC_CALL_CALLERID="FERXGO ACIL DURUM <5555>"
 *   PANIC_OUTBOUND_PREFIX=            (dış hat öneki gerekiyorsa; genelde boş)
 *
 * Santral kurulu/etkin değilse ['ok'=>false] döner → arayüz tel: ile telefonun
 * çeviricisine düşer (davranış bozulmaz).
 */
class ClickToCallService
{
    /**
     * @return array{ok: bool, message?: string}
     */
    public function callToOperator(string $targetPhone): array
    {
        $cfg = config('services.panic.click_to_call');

        if (! ($cfg['enabled'] ?? false)) {
            return ['ok' => false, 'message' => 'Click-to-call kapalı (PANIC_CLICK_TO_CALL_ENABLED).'];
        }

        $ami = $cfg['ami'] ?? [];
        foreach (['host', 'username', 'secret', 'operator_channel'] as $k) {
            if (empty($ami[$k])) {
                return ['ok' => false, 'message' => "Santral yapılandırması eksik: {$k}"];
            }
        }

        $target = $this->formatTarget($targetPhone, $ami['outbound_prefix'] ?? '');
        if ($target === '') {
            return ['ok' => false, 'message' => 'Aranacak numara geçersiz.'];
        }

        return $this->amiOriginate($ami, $target);
    }

    /**
     * Asterisk AMI'ye ham TCP ile bağlanır, Originate yollar.
     *
     * @param array<string,mixed> $ami
     * @return array{ok: bool, message?: string}
     */
    private function amiOriginate(array $ami, string $target): array
    {
        $host    = (string) $ami['host'];
        $port    = (int) ($ami['port'] ?? 5038);
        $timeout = 6;

        $errno = 0; $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! $fp) {
            Log::error('[ClickToCall] AMI bağlanamadı', ['host' => $host, 'port' => $port, 'err' => $errstr]);
            return ['ok' => false, 'message' => "Santrala bağlanılamadı ({$errstr})."];
        }
        stream_set_timeout($fp, $timeout);

        $write = function (array $lines) use ($fp) {
            fwrite($fp, implode("\r\n", $lines) . "\r\n\r\n");
        };

        // Login
        $write([
            'Action: Login',
            'Username: ' . $ami['username'],
            'Secret: ' . $ami['secret'],
        ]);
        $loginResp = $this->readResponse($fp);
        if (stripos($loginResp, 'Success') === false) {
            fclose($fp);
            Log::error('[ClickToCall] AMI login başarısız', ['resp' => trim($loginResp)]);
            return ['ok' => false, 'message' => 'Santral kimlik doğrulaması başarısız.'];
        }

        // Originate: önce operatör dahilisi çalar; açınca hedef numara aranıp köprülenir
        $callerId = (string) ($ami['caller_id'] ?? 'FERXGO ACIL DURUM');
        $write([
            'Action: Originate',
            'Channel: ' . $ami['operator_channel'],
            'Context: ' . ($ami['context'] ?? 'from-internal'),
            'Exten: ' . $target,
            'Priority: 1',
            'CallerID: ' . $callerId,
            'Async: true',
            'Timeout: 30000',
        ]);
        $origResp = $this->readResponse($fp);

        $write(['Action: Logoff']);
        fclose($fp);

        if (stripos($origResp, 'Success') === false && stripos($origResp, 'Originate successfully') === false) {
            Log::error('[ClickToCall] Originate başarısız', ['resp' => trim($origResp)]);
            return ['ok' => false, 'message' => 'Çağrı başlatılamadı.'];
        }

        Log::info('[ClickToCall] Çağrı başlatıldı', ['target' => $target, 'channel' => $ami['operator_channel']]);
        return ['ok' => true];
    }

    private function readResponse($fp): string
    {
        $buffer = '';
        $start = microtime(true);
        while (! feof($fp)) {
            $line = fgets($fp, 4096);
            if ($line === false) break;
            $buffer .= $line;
            // AMI blokları boş satırla biter
            if (str_ends_with($buffer, "\r\n\r\n")) break;
            if (microtime(true) - $start > 6) break;
        }
        return $buffer;
    }

    /**
     * Numarayı santralın beklediği ulusal formata çevirir (+90/90 → 0..., boşlukları temizler).
     */
    private function formatTarget(string $phone, string $prefix): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (strlen($digits) === 10 && $digits[0] === '5') {
            $digits = '0' . $digits;
        }
        return $digits === '' ? '' : $prefix . $digits;
    }
}
