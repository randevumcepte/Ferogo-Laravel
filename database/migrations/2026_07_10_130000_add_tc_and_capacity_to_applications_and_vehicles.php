<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TC kimlik + araç yolcu kapasitesi:
 *   - driver_applications: tc_no (11 hane) + vehicle_capacity (kaç yolcu)
 *   - vehicles: capacity (araç toplam yolcu kapasitesi — sürücü hariç)
 *
 * TC hem sürücü hem yolcu için mevzuat/mali kayıt açısından zorunlu (paylaşımlı
 * yolculuk vergi bildirimi için Maliye Bakanlığı kararı 7 Ağustos 2024).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->string('tc_no', 11)->nullable()->after('full_name');
            $table->unsignedTinyInteger('vehicle_capacity')->nullable()->after('vehicle_color');

            $table->index('tc_no');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            // Araç fiziksel kapasitesi (sürücü hariç, yolcu sayısı).
            $table->unsignedTinyInteger('capacity')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->dropIndex(['tc_no']);
            $table->dropColumn(['tc_no', 'vehicle_capacity']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
