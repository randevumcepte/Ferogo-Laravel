<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Sürücü başvurusuna cinsiyet ekle. Onaylanınca User.gender'a yansır,
     * kadın olan sürücüler için "sadece kadın yolcu" (women_passengers_only)
     * opsiyonunu admin panelden aktif edebiliriz.
     */
    public function up(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            // 'unknown' = başvuruda söylenmemiş; enum yerine string tuttum çünkü
            // ileride 'other' / 'prefer_not_to_say' eklemek istersek migration
            // yeniden gerekmez.
            $table->string('gender', 20)->nullable()->after('birth_year');
        });
    }

    public function down(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
