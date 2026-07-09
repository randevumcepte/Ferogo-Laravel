<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saat/gün hedefleme. Boş = her zaman.
 * target_hours: [18,19,20,21] gibi saatler (0-23) → sadece bu saatlerde gösterilir.
 * target_days: [1,2,3,4,5] gibi haftanın günleri (0=Pazar..6=Cumartesi).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->json('target_hours')->nullable()->after('is_exclusive');
            $table->json('target_days')->nullable()->after('target_hours');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn(['target_hours', 'target_days']);
        });
    }
};
