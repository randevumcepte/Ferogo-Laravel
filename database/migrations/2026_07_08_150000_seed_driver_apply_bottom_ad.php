<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Sürücü Olun — Alt Reklam Alanı" için örnek kayıt ekler ki admin listesinde
 * çıksın ve yönetilebilsin. Zaten varsa dokunmaz (idempotent).
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::table('advertisements')->where('placement', 'driver_apply_bottom')->exists()) {
            return;
        }

        DB::table('advertisements')->insert([
            'placement'    => 'driver_apply_bottom',
            'sector'       => 'sigorta',
            'title'        => 'Ticari araç sigortasında sürücüye özel avantaj',
            'sponsor_name' => 'Ege Sigorta',
            'description'  => 'Aracını uygun fiyata sigortala, yola güvenle çık.',
            'cta_text'     => 'Teklif Al',
            'image_only'   => true,
            'link_url'     => 'https://ferxgo.com',
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
            ->where('placement', 'driver_apply_bottom')
            ->where('sponsor_name', 'Ege Sigorta')
            ->delete();
    }
};
