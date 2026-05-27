<?php
/**
 * config/services.php dosyasına 'google_maps_key' binding'ini güvenli şekilde ekler.
 * Eğer zaten varsa dokunmaz.
 */

$file = $argv[1] ?? __DIR__ . '/config/services.php';

if (! file_exists($file)) {
    fwrite(STDERR, "Dosya bulunamadı: {$file}\n");
    exit(1);
}

$content = file_get_contents($file);

if (str_contains($content, 'google_maps_key') || str_contains($content, 'GOOGLE_MAPS_API_KEY')) {
    echo "✓ google_maps_key zaten kayıtlı.\n";
    echo "--- İlgili satırlar:\n";
    foreach (explode("\n", $content) as $i => $line) {
        if (str_contains($line, 'google_maps_key') || str_contains($line, 'GOOGLE_MAPS_API_KEY')) {
            echo ($i + 1) . ": " . $line . "\n";
        }
    }
    exit(0);
}

// En son ']' satırından önce ekle
$insertion = "\n    'google_maps_key' => env('GOOGLE_MAPS_API_KEY'),\n";

// Find the LAST closing `];` of the return array
if (! preg_match('/\n\];?\s*$/s', $content)) {
    fwrite(STDERR, "config/services.php beklenmedik format, manuel düzenle.\n");
    exit(2);
}

// Backup
copy($file, $file . '.bak');

$newContent = preg_replace('/(\n\];?\s*)$/s', $insertion . '$1', $content, 1);
file_put_contents($file, $newContent);

echo "✓ google_maps_key eklendi.\n";
echo "--- Yedek: {$file}.bak\n";
echo "--- Yeni satırlar (son 5):\n";
$lines = explode("\n", $newContent);
foreach (array_slice($lines, -8) as $line) {
    echo "    " . $line . "\n";
}
