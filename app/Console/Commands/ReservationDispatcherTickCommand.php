<?php

namespace App\Console\Commands;

use App\Modules\Booking\Services\ReservationDispatcherService;
use Illuminate\Console\Command;

/**
 * Rezervasyon dispatcher cron tick — her dakika çalışır.
 *
 *   php artisan reservations:tick
 *
 * Yaptıkları:
 *   - T-24h penceresine giren accepted rezervasyonları reconfirm akışına sokar
 *   - Reconfirm deadline geçenleri pool'a geri atar (sürücü teyit etmedi)
 *   - T-2h penceresine giren confirmed rezervasyonları imminent yapar (maskeli arama açılır)
 *   - 12 saatten uzun süredir pool'da olan rezervasyonları unmatched yapar (müşteriye iade)
 *
 * Server crontab (Laravel scheduler üzerinden):
 *   routes/console.php → Schedule::command('reservations:tick')->everyMinute()
 */
class ReservationDispatcherTickCommand extends Command
{
    protected $signature = 'reservations:tick {--quiet : Çıktıyı bastır}';

    protected $description = 'Rezervasyon dispatcher tick: reconfirm + imminent + unmatched akışları';

    public function handle(ReservationDispatcherService $dispatcher): int
    {
        $reconfirmRequested = $dispatcher->tickReconfirm();
        $reconfirmTimedOut  = $dispatcher->tickReconfirmTimeout();
        $imminent           = $dispatcher->tickImminent();
        $unmatched          = $dispatcher->tickUnmatched();

        if (! $this->option('quiet')) {
            $this->info(sprintf(
                '[%s] reservations tick → reconfirm_req:%d  reconfirm_timeout:%d  imminent:%d  unmatched:%d',
                now()->toIso8601String(),
                $reconfirmRequested,
                $reconfirmTimedOut,
                $imminent,
                $unmatched,
            ));
        }

        return self::SUCCESS;
    }
}
