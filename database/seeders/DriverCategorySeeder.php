<?php

namespace Database\Seeders;

use App\Modules\Driver\Models\DriverCategory;
use Illuminate\Database\Seeder;

/**
 * FerXGo sürücü kategorileri: Otomobil, Sarı Taksi, Motosiklet.
 * Her kategori kendi ehliyet sınıfı ve belge listesiyle gelir.
 *
 * Idempotent: updateOrCreate ile tekrar tekrar çalıştırılabilir.
 *   php artisan db:seed --class=Database\\Seeders\\DriverCategorySeeder
 */
class DriverCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug'                   => 'otomobil',
                'name'                   => 'Otomobil',
                'emoji'                  => '🚗',
                'description'            => 'Özel binek araç ile paylaşımlı yolculuk. B sınıfı ehliyet yeterli.',
                'required_license_class' => 'B',
                'requires_src'           => false,
                'requires_helmet'        => false,
                'required_documents'     => [
                    'license'         => 'Ehliyet (B)',
                    'ruhsat'          => 'Araç ruhsatı',
                    'insurance'       => 'Trafik sigortası',
                    'inspection'      => 'Fenni muayene',
                    'criminal_record' => 'Adli sicil kaydı',
                ],
                'sort_order' => 10,
            ],
            [
                'slug'                   => 'sari_taksi',
                'name'                   => 'Sarı Taksi',
                'emoji'                  => '🚕',
                'description'            => 'T plakalı ticari taksi ile paylaşımlı yolculuk. SRC-2 belgesi zorunlu.',
                'required_license_class' => 'B',
                'requires_src'           => true,
                'requires_helmet'        => false,
                'required_documents'     => [
                    'license'         => 'Ehliyet (B)',
                    'src'             => 'SRC-2 sertifikası (zorunlu)',
                    'ruhsat'          => 'Araç ruhsatı (T plakalı)',
                    'taksi_plaka'     => 'Ticari taksi plaka izin belgesi',
                    'taksimetre'      => 'Taksimetre kalibrasyon belgesi',
                    'oda_kaydi'       => 'İzmir Taksiciler Esnaf Odası kaydı',
                    'insurance'       => 'Trafik sigortası + Koltuk ferdi kaza',
                    'inspection'      => 'Fenni muayene',
                    'criminal_record' => 'Adli sicil kaydı',
                    'psychotechnic'   => 'Psikoteknik raporu',
                ],
                'sort_order' => 20,
            ],
            [
                'slug'                   => 'motosiklet',
                'name'                   => 'Motosiklet',
                'emoji'                  => '🏍',
                'description'            => 'Motosiklet ile hızlı paket/kısa mesafe paylaşımı. A2 veya A sınıfı ehliyet.',
                'required_license_class' => 'A2',
                'requires_src'           => false,
                'requires_helmet'        => true,
                'required_documents'     => [
                    'license'         => 'Ehliyet (A2 veya A)',
                    'ruhsat'          => 'Motor ruhsatı',
                    'insurance'       => 'Trafik sigortası',
                    'inspection'      => 'Fenni muayene',
                    'criminal_record' => 'Adli sicil kaydı',
                    'kask'            => 'Kask fotoğrafı (kullandığın kaskı çek)',
                ],
                'sort_order' => 30,
            ],
        ];

        foreach ($categories as $c) {
            DriverCategory::updateOrCreate(
                ['slug' => $c['slug']],
                $c + ['is_active' => true],
            );
        }

        $this->command?->info('  ✓ 3 sürücü kategorisi seed edildi (Otomobil, Sarı Taksi, Motosiklet).');
    }
}
