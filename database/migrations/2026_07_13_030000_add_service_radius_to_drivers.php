<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sürücünün kendi belirlediği "görünürlük/hizmet çapı" (km).
 * Sürücü yalnızca bu çap içindeki alış noktaları için aday gösterilir/eşleşir.
 * Varsayılan 5 km; uygulamada 2–20 km arası ayarlanabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->decimal('service_radius_km', 4, 1)->default(5.0)->after('last_location_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('service_radius_km');
        });
    }
};
