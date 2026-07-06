<?php

/**
 * Reklam teşhis + zorla cache temizleme (tarayıcıdan).
 *   https://ferxgo.com/diag-ads.php?key=ferxgo-ads-2026-migrate
 *
 * Ne yapar:
 *   1) Sunucudaki git commit'i + ad-slot.blade.php'nin yeni tasarım olup olmadığını gösterir
 *   2) Veritabanındaki reklamları (görsel var mı) listeler
 *   3) view/config/cache temizler + OPcache reset eder
 *
 * ⚠️ İş bitince bu dosyayı sil.
 */

if (($_GET['key'] ?? '') !== 'ferxgo-ads-2026-migrate') {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(120);

// 1) Deploy kontrolü — dosya sisteminden (Laravel'e gerek yok)
$head = @trim((string) @file_get_contents(__DIR__ . '/../.git/refs/heads/main'));
echo "Sunucudaki git commit (main): " . ($head !== '' ? $head : '(okunamadi - packed olabilir)') . "\n";
echo "Beklenen (en son push): c139ae2 veya sonrasi\n\n";

$blade = (string) @file_get_contents(__DIR__ . '/../resources/views/partials/ad-slot.blade.php');
$yeniTasarim = str_contains($blade, 'Sponsorlu');
echo "ad-slot.blade.php YENI tasarim mi? " . ($yeniTasarim ? "EVET ✅" : "HAYIR ❌  → kod sunucuya HENUZ inmemis (cron pull bekleniyor)") . "\n";
echo "ad-slot.blade.php boyut: " . strlen($blade) . " byte\n\n";

// 2) Laravel boot + reklamları oku
require __DIR__ . '/../vendor/autoload.php';
/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';
/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "== Veritabanindaki reklamlar ==\n";
try {
    $ads = \App\Modules\Marketing\Models\Advertisement::query()
        ->orderBy('placement')
        ->get(['placement', 'sponsor_name', 'image_url', 'is_active']);

    if ($ads->isEmpty()) {
        echo "  (hic reklam yok — seeder calismamis)\n";
    }
    foreach ($ads as $a) {
        echo "  - {$a->placement} | {$a->sponsor_name} | gorsel=" . ($a->image_url ?: 'YOK ❌')
            . " | aktif=" . ($a->is_active ? '1' : '0') . "\n";
    }
} catch (\Throwable $e) {
    echo "  Sorgu hatasi: " . $e->getMessage() . "\n";
}

// 3) Cache + OPcache temizle
echo "\n== optimize:clear ==\n";
try {
    $kernel->call('optimize:clear');
    echo $kernel->output();
} catch (\Throwable $e) {
    echo "optimize:clear hata: " . $e->getMessage() . "\n";
}

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache_reset() ✅ calisti\n";
} else {
    echo "OPcache aktif degil (sorun yok)\n";
}

echo "\n✅ BITTI. Simdi reklam sayfasinda Ctrl+F5 yap. Bu ciktiyi bana yapistir.\n";
