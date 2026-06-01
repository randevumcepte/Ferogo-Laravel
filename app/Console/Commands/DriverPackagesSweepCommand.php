<?php

namespace App\Console\Commands;

use App\Modules\Payment\Services\DriverPackageService;
use Illuminate\Console\Command;

class DriverPackagesSweepCommand extends Command
{
    protected $signature = 'driver-packages:sweep';

    protected $description = 'Süresi dolan sürücü paketlerini expire et + sürücüleri offline yap. Her dakika çalışır.';

    public function handle(DriverPackageService $service): int
    {
        $result = $service->sweepExpired();

        if ($result['packages_expired'] > 0 || $result['drivers_offlined'] > 0) {
            $this->info(sprintf(
                'Sweep: %d paket expire, %d sürücü offline.',
                $result['packages_expired'],
                $result['drivers_offlined'],
            ));
        }

        return self::SUCCESS;
    }
}
