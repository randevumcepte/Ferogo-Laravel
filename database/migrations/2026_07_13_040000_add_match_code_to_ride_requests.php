<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eşleşme kodu (Uber/Bolt "PIN to start" benzeri).
 * Sürücü teklifi kabul edince 4 haneli kod üretilir; yalnızca YOLCUYA gösterilir.
 * Sürücü buluşmada bu kodu girer → doğruysa yolculuk başlar (mevcut started_at set edilir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->string('match_code', 4)->nullable()->after('agreed_fare');
        });
    }

    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropColumn('match_code');
        });
    }
};
