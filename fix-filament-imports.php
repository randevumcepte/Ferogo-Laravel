<?php
/**
 * Fix Filament v4 generated resource imports.
 *
 * v4'ün `make:filament-resource` komutu FQCN'i parse ederken model namespace'ine
 * yanlışlıkla App\Models\ ön ekini ekliyor:
 *   - App\Models\App\Modules\Shared\Models\City  (yanlış)
 *     → App\Modules\Shared\Models\City           (doğru)
 *   - App\Models\App\Models\User                  (yanlış)
 *     → App\Models\User                           (doğru)
 *
 * Bu script tüm resource/schema/table/page dosyalarını gezer ve düzeltir.
 *
 * Kullanım:
 *   /opt/php83/bin/php fix-filament-imports.php /full/path/to/Filament/Resources
 */

$dir = $argv[1] ?? __DIR__ . '/app/Filament/Resources';
$dir = rtrim($dir, '/');

if (! is_dir($dir)) {
    fwrite(STDERR, "Dizin bulunamadı: {$dir}\n");
    exit(1);
}

$replacements = [
    'App\\Models\\App\\Modules\\' => 'App\\Modules\\',
    'App\\Models\\App\\Models\\' => 'App\\Models\\',
];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$fixed = 0;
$scanned = 0;

foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $scanned++;

    $content = file_get_contents($file->getPathname());
    $original = $content;

    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo "✓ " . str_replace($dir . '/', '', $file->getPathname()) . "\n";
        $fixed++;
    }
}

echo "\n";
echo "Taranan: {$scanned} dosya\n";
echo "Düzeltilen: {$fixed} dosya\n";
