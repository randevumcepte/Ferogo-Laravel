<?php

namespace App\Console\Commands;

use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use Illuminate\Console\Command;

/**
 * Voice Telekom credential'larını test etmek için:
 *   php artisan sms:test 0532XXXXXXX
 * Test mesajı gönderir; başarı durumunda pkgID basar, hatada VT mesajını gösterir.
 */
class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {phone} {message? : Özel mesaj (varsayılan otomatik)}';
    protected $description = 'Voice Telekom üzerinden test SMS gönder';

    public function handle(VoiceTelekomClient $client): int
    {
        $phone   = (string) $this->argument('phone');
        $message = (string) ($this->argument('message')
            ?? 'FerXGo SMS testi. Bu mesaji aliyorsan entegrasyon calisiyor.');

        $cfg = config('services.voicetelekom');
        $this->info("Provider: Voice Telekom");
        $this->info("Host: {$cfg['host']}:{$cfg['port']}");
        $this->info("Sender: {$cfg['sender']}");
        $this->info("Enabled: " . ($cfg['enabled'] ? 'EVET' : 'HAYIR (sadece log/cache)'));
        $this->info("Username: " . ($cfg['username'] ? substr($cfg['username'], 0, 3) . '***' : '(boş)'));
        $this->line('');

        if (! $cfg['enabled']) {
            $this->warn('VOICETELEKOM_ENABLED=false — gerçek SMS gönderilmeyecek. .env\'i güncelle ve tekrar dene.');
            return self::FAILURE;
        }

        $this->info("Gönderiliyor: {$phone}");
        $result = $client->sendSingle($phone, $message);

        if ($result['ok']) {
            $this->info('✅ Başarılı.');
            $this->line('   pkg_id: ' . ($result['pkg_id'] ?? '-'));
            return self::SUCCESS;
        }

        $this->error('❌ Başarısız: ' . ($result['message'] ?? 'unknown'));
        $this->line('Detay için: storage/logs/laravel.log');
        return self::FAILURE;
    }
}
