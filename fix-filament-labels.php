<?php
/**
 * Filament v4 Resource'lara Türkçe etiket, grup, icon ve temiz URL ekler.
 *
 * Kullanım:
 *   /opt/php83/bin/php fix-filament-labels.php /path/to/app/Filament/Resources
 */

$resourcesDir = $argv[1] ?? __DIR__ . '/app/Filament/Resources';
$resourcesDir = rtrim($resourcesDir, '/');

if (! is_dir($resourcesDir)) {
    fwrite(STDERR, "Dizin bulunamadı: {$resourcesDir}\n");
    exit(1);
}

/**
 * Her resource için config:
 *   slug              - URL parçası (örn. /admin/cities)
 *   modelLabel        - Tekil ad (örn. "Şehir")
 *   pluralModelLabel  - Çoğul ad (örn. "Şehirler")
 *   navigationLabel   - Menüde görünecek isim
 *   navigationGroup   - Menü grubu (Operasyon / Konfigürasyon / Finans / Sistem)
 *   navigationSort    - Grup içi sıralama
 *   icon              - Heroicon enum değeri (Outlined* prefix'i)
 */
$config = [
    // === Operasyon (günlük iş) ===
    'RideResource' => [
        'slug' => 'rides',
        'modelLabel' => 'Rezervasyon',
        'pluralModelLabel' => 'Rezervasyonlar',
        'navigationLabel' => 'Rezervasyonlar',
        'navigationGroup' => 'Operasyon',
        'navigationSort' => 10,
        'icon' => 'OutlinedCalendarDays',
    ],
    'DriverResource' => [
        'slug' => 'drivers',
        'modelLabel' => 'Sürücü',
        'pluralModelLabel' => 'Sürücüler',
        'navigationLabel' => 'Sürücüler',
        'navigationGroup' => 'Operasyon',
        'navigationSort' => 20,
        'icon' => 'OutlinedUserGroup',
    ],
    'VehicleResource' => [
        'slug' => 'vehicles',
        'modelLabel' => 'Araç',
        'pluralModelLabel' => 'Araçlar',
        'navigationLabel' => 'Araçlar',
        'navigationGroup' => 'Operasyon',
        'navigationSort' => 30,
        'icon' => 'OutlinedTruck',
    ],

    // === Konfigürasyon (kurulum) ===
    'CityResource' => [
        'slug' => 'cities',
        'modelLabel' => 'Şehir',
        'pluralModelLabel' => 'Şehirler',
        'navigationLabel' => 'Şehirler',
        'navigationGroup' => 'Konfigürasyon',
        'navigationSort' => 10,
        'icon' => 'OutlinedMapPin',
    ],
    'VehicleClassResource' => [
        'slug' => 'vehicle-classes',
        'modelLabel' => 'Araç Sınıfı',
        'pluralModelLabel' => 'Araç Sınıfları',
        'navigationLabel' => 'Araç Sınıfları',
        'navigationGroup' => 'Konfigürasyon',
        'navigationSort' => 20,
        'icon' => 'OutlinedSquares2x2',
    ],
    'PricingRuleResource' => [
        'slug' => 'pricing-rules',
        'modelLabel' => 'Tarife',
        'pluralModelLabel' => 'Tarifeler',
        'navigationLabel' => 'Tarifeler',
        'navigationGroup' => 'Konfigürasyon',
        'navigationSort' => 30,
        'icon' => 'OutlinedBanknotes',
    ],
    'ExtraResource' => [
        'slug' => 'extras',
        'modelLabel' => 'Ekstra',
        'pluralModelLabel' => 'Ekstralar',
        'navigationLabel' => 'Ekstralar',
        'navigationGroup' => 'Konfigürasyon',
        'navigationSort' => 40,
        'icon' => 'OutlinedPlusCircle',
    ],

    // === Finans ===
    'PaymentResource' => [
        'slug' => 'payments',
        'modelLabel' => 'Ödeme',
        'pluralModelLabel' => 'Ödemeler',
        'navigationLabel' => 'Ödemeler',
        'navigationGroup' => 'Finans',
        'navigationSort' => 10,
        'icon' => 'OutlinedCreditCard',
    ],

    // === Sistem ===
    'UserResource' => [
        'slug' => 'users',
        'modelLabel' => 'Kullanıcı',
        'pluralModelLabel' => 'Kullanıcılar',
        'navigationLabel' => 'Kullanıcılar',
        'navigationGroup' => 'Sistem',
        'navigationSort' => 10,
        'icon' => 'OutlinedUsers',
    ],
];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resourcesDir));
$fixed = 0;
$skipped = 0;

foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $name = $file->getFilename();

    // Sadece *Resource.php dosyalarını işle, Schema/Table/Page dosyalarını atla
    if (! preg_match('/^(\w+)Resource\.php$/', $name, $m)) {
        continue;
    }

    $resourceName = $name;
    $configKey = basename($resourceName, '.php');

    if (! isset($config[$configKey])) {
        echo "− atlandı (config yok): {$resourceName}\n";
        $skipped++;
        continue;
    }

    $cfg = $config[$configKey];
    $content = file_get_contents($file->getPathname());
    $original = $content;

    // Etiket properties'lerini build et
    $props = "\n";
    $props .= "    protected static ?string \$slug = '{$cfg['slug']}';\n\n";
    $props .= "    protected static ?string \$modelLabel = '{$cfg['modelLabel']}';\n\n";
    $props .= "    protected static ?string \$pluralModelLabel = '{$cfg['pluralModelLabel']}';\n\n";
    $props .= "    protected static ?string \$navigationLabel = '{$cfg['navigationLabel']}';\n\n";
    $props .= "    protected static string|\UnitEnum|null \$navigationGroup = '{$cfg['navigationGroup']}';\n\n";
    $props .= "    protected static ?int \$navigationSort = {$cfg['navigationSort']};\n";

    // 1) Önce eski properties'leri temizle (idempotent)
    $content = preg_replace('/\n    protected static \?string \$slug = .+?;\n/', '', $content);
    $content = preg_replace('/\n    protected static \?string \$modelLabel = .+?;\n/', '', $content);
    $content = preg_replace('/\n    protected static \?string \$pluralModelLabel = .+?;\n/', '', $content);
    $content = preg_replace('/\n    protected static \?string \$navigationLabel = .+?;\n/', '', $content);
    $content = preg_replace('/\n    protected static string\|\\\\UnitEnum\|null \$navigationGroup = .+?;\n/', '', $content);
    $content = preg_replace('/\n    protected static \?int \$navigationSort = .+?;\n/', '', $content);

    // 2) $model = X::class; satırından sonra ekle
    $content = preg_replace(
        '/(protected static \?string \$model = [^;]+;\n)/',
        "$1{$props}",
        $content,
        1,
        $count
    );

    if ($count === 0) {
        echo "✗ \$model line bulunamadı: {$resourceName}\n";
        continue;
    }

    // 3) Icon güncelle (Heroicon::Outlined* pattern'ini değiştir)
    $content = preg_replace(
        '/Heroicon::Outlined\w+/',
        'Heroicon::' . $cfg['icon'],
        $content
    );

    if ($content === $original) {
        echo "− değişmedi: {$resourceName}\n";
        continue;
    }

    file_put_contents($file->getPathname(), $content);
    echo "✓ {$resourceName} — Grup: {$cfg['navigationGroup']}, Etiket: {$cfg['pluralModelLabel']}\n";
    $fixed++;
}

echo "\n";
echo "Düzeltilen: {$fixed} resource\n";
echo "Atlanan: {$skipped} dosya\n";
