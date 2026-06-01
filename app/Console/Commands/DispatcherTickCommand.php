<?php

namespace App\Console\Commands;

use App\Modules\Booking\Services\DispatcherService;
use Illuminate\Console\Command;

/**
 * Dispatcher tick — her dakika çalışır.
 *
 *   php artisan dispatcher:tick
 *
 * Yaptıkları:
 *   - Süresi gelmiş pending taleplerini havuza yayar
 *   - Stale awaiting_customer_reconfirm taleplerini iptal eder
 *   - Stale pool_expanded taleplerini exhausted yapar
 *
 * Crontab'a ekleyin (server-side):
 *   * * * * * cd /path/to/Ferogo-Laravel && php artisan dispatcher:tick >> storage/logs/dispatcher.log 2>&1
 */
class DispatcherTickCommand extends Command
{
    protected $signature = 'dispatcher:tick {--quiet : Çıktıyı bastır}';

    protected $description = 'Dispatcher cron tick: pool expansion, stale reconfirm cleanup, stale pool offers cleanup';

    public function handle(DispatcherService $dispatcher): int
    {
        $expanded = $dispatcher->tickPendingExpansions();
        $reconfirmCancelled = $dispatcher->tickStaleReconfirms();
        $poolExhausted = $dispatcher->tickStalePoolOffers();

        if (! $this->option('quiet')) {
            $this->info(sprintf(
                '[%s] dispatch tick → pool_expanded:%d  reconfirm_cancelled:%d  pool_exhausted:%d',
                now()->toIso8601String(),
                $expanded,
                $reconfirmCancelled,
                $poolExhausted,
            ));
        }

        return self::SUCCESS;
    }
}
