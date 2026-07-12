<?php

namespace App\Console\Commands;

use App\Modules\Notification\Models\NotificationCampaign;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Zamanı gelmiş (scheduled) bildirim kampanyalarını gönderir.
 * routes/console.php → Schedule::command('notifications:dispatch')->everyMinute()
 */
class NotificationsDispatchCommand extends Command
{
    protected $signature = 'notifications:dispatch {--quiet : Çıktıyı bastır}';

    protected $description = 'Zamanlanmış bildirim kampanyalarını gönderir (scheduled → sent).';

    public function handle(NotificationService $service): int
    {
        $due = NotificationCampaign::query()->due()->limit(20)->get();

        $sent = 0;
        foreach ($due as $campaign) {
            $service->dispatchCampaign($campaign);
            $sent++;
        }

        if (! $this->option('quiet')) {
            $this->info(sprintf('[%s] notifications:dispatch → %d kampanya gönderildi.', now()->toIso8601String(), $sent));
        }

        return self::SUCCESS;
    }
}
