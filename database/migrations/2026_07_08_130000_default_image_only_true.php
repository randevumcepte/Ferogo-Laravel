<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Tam görsel" varsayılanı AÇIK yapılır: reklamverenler genelde komple hazır banner
 * yüklüyor; tam görsel varsayılan olsun ki elle anahtar açmaya gerek kalmasın.
 * Mevcut tüm reklamlar da tam görsele çevrilir.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('advertisements')->update(['image_only' => true]);
    }

    public function down(): void
    {
        DB::table('advertisements')->update(['image_only' => false]);
    }
};
