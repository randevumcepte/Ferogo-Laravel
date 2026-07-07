<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Örnek "popup" reklamı ekler ki açılır pencere alanı sitede hemen görünsün.
 * Zaten bir popup reklamı varsa hiçbir şey yapmaz (idempotent).
 * Süper adminden (Pazarlama → Reklam Alanları) düzenlenebilir / pasife alınabilir / silinebilir.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('advertisements')->where('placement', 'popup')->exists();
        if ($exists) {
            return;
        }

        DB::table('advertisements')->insert([
            'placement'    => 'popup',
            'sector'       => 'sigorta',
            'title'        => 'Aracını en iyi fiyata sigortala',
            'sponsor_name' => 'Ege Sigorta',
            'description'  => 'Dakikalar içinde teklif al, İzmir’e özel indirimden yararlan.',
            'image_url'    => 'https://picsum.photos/seed/ferxgo-popup/800/520',
            'link_url'     => 'https://ferxgo.com',
            'cta_text'     => 'Fiyat Al',
            'is_active'    => true,
            'sort_order'   => 0,
            'impressions'  => 0,
            'clicks'       => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('advertisements')
            ->where('placement', 'popup')
            ->where('sponsor_name', 'Ege Sigorta')
            ->delete();
    }
};
