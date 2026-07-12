<?php

namespace App\Console\Commands;

use App\Modules\Mobile\Models\DeviceToken;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * FCM push'ın uçtan uca çalıştığını doğrular — GERÇEK sistemi (NotificationService)
 * kullanır, yani inbox kaydı + push aynı üretim yolundan gider.
 *
 *   php artisan fcm:test 42                 → user 42'nin cihazlarına test push
 *   php artisan fcm:test 42 --title=Selam --body=Deneme
 *
 * services.firebase.enabled=false ise MOCK moda düşer (loga yazar, göndermez).
 * Canlı gönderim için .env: FCM_ENABLED=true, FCM_PROJECT_ID=ferxgo + key dosyası.
 */
class FcmTestCommand extends Command
{
    protected $signature = 'fcm:test {user_id : Push gönderilecek kullanıcı ID}
                            {--title=FerXGo : Bildirim başlığı}
                            {--body=Test bildirimi 🚕 : Bildirim metni}';

    protected $description = 'Bir kullanıcının cihazlarına test FCM push gönderir (NotificationService üzerinden)';

    public function handle(NotificationService $notifications): int
    {
        $userId = (int) $this->argument('user_id');

        $this->line('FCM enabled : ' . (config('services.firebase.enabled') ? 'true' : 'MOCK (false)'));
        $this->line('Project ID  : ' . (config('services.firebase.project_id') ?: '(boş!)'));

        $tokens = DeviceToken::where('user_id', $userId)->whereNotNull('fcm_token')->count();
        $this->line("Kullanıcının fcm_token'lı cihaz sayısı: {$tokens}");

        if ($tokens === 0) {
            $this->warn('Bu kullanıcıda kayıtlı fcm_token yok — önce mobil app ile login olup token kaydettir.');
        }

        $result = $notifications->deliver([$userId], [
            'type'  => 'test',
            'title' => (string) $this->option('title'),
            'body'  => (string) $this->option('body'),
            'data'  => ['type' => 'test'],
        ]);

        $this->info('Sonuç: ' . json_encode($result));
        $this->comment('Not: MOCK modda telefona bildirim GİTMEZ, sadece log/inbox yazılır.');

        return self::SUCCESS;
    }
}
