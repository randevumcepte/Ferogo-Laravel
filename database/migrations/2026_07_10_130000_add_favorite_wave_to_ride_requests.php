<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favori-öncelikli dispatch (sadakat sistemi):
 * Talep önce yolcunun ONLINE favori sürücülerine dalga halinde gider.
 * is_favorite_wave = true iken pool_candidate_driver_ids favori sürücülerdir;
 * dönüş olmazsa cron (tickFavoriteWaves) yakındaki havuza düşürür (flag false olur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->boolean('is_favorite_wave')->default(false)->after('pool_expanded_at');
        });
    }

    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropColumn('is_favorite_wave');
        });
    }
};
