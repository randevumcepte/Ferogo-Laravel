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

/*
 * Rezervasyon dispatcher — T-24h reconfirm, T-2h imminent, 12h unmatched
 * tick'leri her dakika çalışır.
 */
Schedule::command('reservations:tick --quiet')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Anlık dispatcher — pending→havuz yayma, favori dalgası→yakın havuz düşürme,
 * stale teklif/reconfirm temizliği. Bu OLMAZSA üç katmanlı eşleştirmenin
 * otomatik geçişleri çalışmaz (seçilen sürücü cevap vermezse talep havuza düşmez).
 */
Schedule::command('dispatcher:tick --quiet')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Bildirim kampanyaları — zamanı gelmiş (scheduled) toplu bildirimleri gönderir.
 */
Schedule::command('notifications:dispatch --quiet')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
