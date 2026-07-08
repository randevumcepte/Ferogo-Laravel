<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reklam rotasyonu + tekellik.
 *
 * - rotation_weight: Bir slotta birden çok aktif reklam varsa, gösterim payı ağırlığı.
 *   (Örn. 3 → eşit ağırlıklı bir reklamın 3 katı sıklıkta çıkar.)
 * - is_exclusive: Bu reklam o slotta TEK gösterilir (rotasyona girmez). Tekellik/Takeover
 *   paketleri için. Bir slotta birden çok exclusive varsa sort_order'a göre ilki gösterilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->unsignedInteger('rotation_weight')->default(1)->after('sort_order');
            $table->boolean('is_exclusive')->default(false)->after('rotation_weight');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn(['rotation_weight', 'is_exclusive']);
        });
    }
};
