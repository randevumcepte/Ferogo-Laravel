<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Her reklam alanı (placement) için örnek kayıt ekler ki süper admin listesinde
 * TÜM alanlar görünsün ve tek tek yönetilebilsin. Bir alan için zaten kayıt varsa
 * o alan atlanır (idempotent). Adminden düzenlenebilir / pasife alınabilir / silinebilir.
 */
return new class extends Migration {
    public function up(): void
    {
        $examples = [
            [
                'placement'    => 'radar_sidebar',
                'sector'       => 'otomotiv',
                'title'        => 'Yeni araç mı istiyorsun?',
                'sponsor_name' => 'İzmir Oto Plaza',
                'description'  => 'Geniş stok, yerinde test sürüşü. Sana en yakın bayi.',
                'cta_text'     => 'İncele',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-sidebar/640/400',
            ],
            [
                'placement'    => 'driver_apply',
                'sector'       => 'akaryakit_lastik',
                'title'        => 'Sürücülere özel: %15 yakıt indirimi',
                'sponsor_name' => 'Petrol Ofisi Bayi',
                'description'  => 'FerXGo sürücüsüysen anlaşmalı istasyonlarda indirim senin.',
                'cta_text'     => 'Fırsatı Gör',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-apply/640/400',
            ],
            [
                'placement'    => 'driver_apply_bottom',
                'sector'       => 'sigorta',
                'title'        => 'Ticari araç sigortasında avantaj',
                'sponsor_name' => 'Ege Sigorta',
                'description'  => 'Aracını uygun fiyata sigortala, yola güvenle çık.',
                'cta_text'     => 'Teklif Al',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-applybottom/640/400',
            ],
            [
                'placement'    => 'driver_panel',
                'sector'       => 'akaryakit_lastik',
                'title'        => 'Aracının bakım zamanı geldi mi?',
                'sponsor_name' => 'Bornova Oto Servis',
                'description'  => 'Periyodik bakım + lastik montaj tek noktada. Randevunu ayır.',
                'cta_text'     => 'Randevu Al',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-driverpanel/640/400',
            ],
            [
                'placement'    => 'sponsored_notification',
                'sector'       => 'banka_finans',
                'title'        => 'Taşıt kredisinde düşük faiz fırsatı',
                'sponsor_name' => 'Ege Bank',
                'description'  => 'Hayalindeki araca bugün başla, uygun taksitlerle öde.',
                'cta_text'     => 'Başvur',
                'image_url'    => 'https://picsum.photos/seed/ferxgo-notif/640/400',
            ],
        ];

        foreach ($examples as $row) {
            $exists = DB::table('advertisements')->where('placement', $row['placement'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('advertisements')->insert(array_merge($row, [
                'link_url'    => 'https://ferxgo.com',
                'is_active'   => true,
                'sort_order'  => 0,
                'impressions' => 0,
                'clicks'      => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('advertisements')
            ->whereIn('placement', ['radar_sidebar', 'driver_apply', 'driver_apply_bottom', 'driver_panel', 'sponsored_notification'])
            ->whereIn('sponsor_name', ['İzmir Oto Plaza', 'Petrol Ofisi Bayi', 'Ege Sigorta', 'Bornova Oto Servis', 'Ege Bank'])
            ->delete();
    }
};
