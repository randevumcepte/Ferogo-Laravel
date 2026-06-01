<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Sürücü paket sistemi — süresi dolan paketleri her dakika tara.
 * Server'da: * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('driver-packages:sweep')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
