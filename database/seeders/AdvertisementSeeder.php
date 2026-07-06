<?php

namespace Database\Seeders;

use App\Modules\Marketing\Models\Advertisement;
use Illuminate\Database\Seeder;

/**
 * Sunumdaki reklam alanlarının canlı görünmesi için örnek sponsor reklamları.
 *
 * updateOrCreate ile idempotent: tekrar çalıştırmak kopya oluşturmaz.
 * Süper adminden (Pazarlama → Reklam Alanları) düzenlenebilir/silinebilir;
 * silinirse sitede altın kesikli "REKLAM ALANINIZ" boş alanı görünür.
 */
class AdvertisementSeeder extends Seeder
{
    public function run(): void
    {
        $ads = [
            [
                'placement'    => 'home_banner',
                'sector'       => 'sigorta',
                'title'        => 'Trafik + Kasko tek pakette — İzmir’e özel %20 indirim',
                'sponsor_name' => 'Ege Sigorta',
                'description'  => 'Aracın için dakikalar içinde teklif al, hemen poliçeni oluştur.',
                'cta_text'     => 'Teklif Al',
                // Örnek görsel — süper adminden gerçek görselle değiştirilmeli (önerilen 1200×628 px)
                'image_url'    => 'https://picsum.photos/seed/ferxgo-sigorta/640/400',
                'sort_order'   => 0,
            ],
            [
                'placement'    => 'ride_tracking',
                'sector'       => 'akaryakit_lastik',
                'title'        => 'Kışa hazır mısın? 4 lastik alana montaj bizden',
                'sponsor_name' => 'Bornova Lastik & Servis',
                'description'  => 'Yolculuğun bitmeden randevunu ayırt, sıra bekleme.',
                'cta_text'     => 'Randevu Al',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-lastik/640/400',
                'sort_order'   => 0,
            ],
            [
                'placement'    => 'radar_map',
                'sector'       => 'otomotiv',
                'title'        => '0 km ve 2. el geniş stok — yerinde test sürüşü',
                'sponsor_name' => 'İzmir Oto Plaza',
                'description'  => 'Sana en yakın bayide bugün test sürüşü fırsatı.',
                'cta_text'     => 'Bayiyi Gör',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-oto/640/400',
                'sort_order'   => 0,
            ],
        ];

        foreach ($ads as $ad) {
            Advertisement::updateOrCreate(
                ['placement' => $ad['placement'], 'sponsor_name' => $ad['sponsor_name']],
                array_merge($ad, ['is_active' => true]),
            );
        }
    }
}
