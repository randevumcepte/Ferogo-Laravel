<?php

/**
 * TEK SEFERLİK migration + seed çalıştırıcı (tarayıcıdan).
 *
 * Sunucuda terminal olmadığı için `php artisan migrate` yerine bunu kullan:
 *   https://ferxgo.com/run-migrate.php?key=ferxgo-ads-2026-migrate
 *
 * ⚠️ ÇALIŞTIKTAN SONRA BU DOSYAYI SUNUCUDAN SİL (güvenlik).
 */

// Basit koruma — anahtarı bilmeyen çalıştıramaz.
if (($_GET['key'] ?? '') !== 'ferxgo-ads-2026-migrate') {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(120);

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

try {
    echo "== php artisan migrate --force ==\n";
    $kernel->call('migrate', ['--force' => true]);
    echo $kernel->output();

    echo "\n== php artisan db:seed --class=AdvertisementSeeder --force ==\n";
    $kernel->call('db:seed', ['--class' => 'AdvertisementSeeder', '--force' => true]);
    echo $kernel->output();

    echo "\n== php artisan optimize:clear ==\n";
    $kernel->call('optimize:clear');
    echo $kernel->output();

    echo "\n\n✅ BITTI. Simdi bu dosyayi (public/run-migrate.php) sunucudan SIL.\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
