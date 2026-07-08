<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tam görsel" modu: açıkça reklam yalnızca yüklenen görseli kaplar (kırpılmadan),
 * üzerine başlık/açıklama/buton binmez. Reklamveren komple hazır banner verdiğinde kullanılır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->boolean('image_only')->default(false)->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn('image_only');
        });
    }
};
