<?php

namespace App\Console\Commands;

use App\Modules\Mobile\Models\DeviceToken;
use App\Modules\Mobile\Services\PushService;
use Illuminate\Console\Command;

/**
 * FCM push'ın uçtan uca çalıştığını doğrular.
 *
 *   php artisan fcm:test 42                 → user 42'nin tüm cihazlarına test push
 *   php artisan fcm:test 42 --title=Selam --body=Deneme
 *
 * Başarılıysa telefonda bildirim düşer ve konsol {sent:1,...} yazar.
 * enabled=false ise hiçbir şey gitmez (config'i .env'de FCM_HTTP_V1_ENABLED=true yap).
 */
class FcmTestCommand extends Command
{
    protected $signature = 'fcm:test {user_id : Push gönderilecek kullanıcı ID}
                            {--title=FerXGo : Bildirim başlığı}
                            {--body=Test bildirimi 🚕 : Bildirim metni}';

    protected $description = 'Bir kullanıcının cihazlarına test FCM push gönderir';

    public function handle(PushService $push): int
    {
        $userId = (int) $this->argument('user_id');

        $this->line('FCM enabled: ' . (config('services.fcm.enabled') ? 'true' : 'false'));
        $this->line('Project ID : ' . config('services.fcm.project_id'));

        $tokens = DeviceToken::where('user_id', $userId)->whereNotNull('fcm_token')->count();
        $this->line("Kullanıcının fcm_token'lı cihaz sayısı: {$tokens}");

        if ($tokens === 0) {
            $this->warn('Bu kullanıcıda kayıtlı fcm_token yok — önce mobil app ile login olup token kaydettir.');
        }

        $summary = $push->sendToUser(
            $userId,
            (string) $this->option('title'),
            (string) $this->option('body'),
            ['type' => 'test', 'sent_from' => 'fcm:test']
        );

        $this->info('Sonuç: ' . json_encode($summary));

        return self::SUCCESS;
    }
}
