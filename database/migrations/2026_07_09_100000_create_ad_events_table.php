<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reklam olay günlüğü (analitik).
 * Her gösterim/tıklama tek satır → alan, zaman(saat/gün), bölge(ilçe), cihaz, kitle kırılımı.
 * Toplam sayaçlar (advertisements.impressions/clicks) hızlı özet için durur;
 * detaylı raporlar bu tablodan üretilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 40)->index();
            $table->string('type', 12)->index();            // impression | click
            $table->timestamp('occurred_at')->index();
            $table->unsignedTinyInteger('hour')->nullable(); // 0-23
            $table->unsignedTinyInteger('dow')->nullable();  // 0=Pazar .. 6=Cumartesi
            $table->string('city', 40)->nullable();
            $table->string('district', 60)->nullable()->index();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('device', 12)->nullable();        // mobile | desktop | app
            $table->string('audience', 12)->nullable();      // customer | driver | guest
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('anon_id', 40)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['advertisement_id', 'type', 'occurred_at']);
            $table->index(['placement', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
    }
};
