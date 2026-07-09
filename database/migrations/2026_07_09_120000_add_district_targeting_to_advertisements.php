<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bölge (ilçe) hedefleme. Boş = tüm bölgeler (genel reklam).
 * target_districts: ['Bornova','Karşıyaka'] → yalnızca bu ilçelerdeki kullanıcıya gösterilir.
 * Kullanıcının ilçesi (konum izni verdiyse) çerezde tutulur; activeFor bölgeye göre seçer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->json('target_districts')->nullable()->after('target_days');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn('target_districts');
        });
    }
};
