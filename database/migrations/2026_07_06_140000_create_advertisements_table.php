<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reklam & Sponsorluk alanları (sunumdaki "REKLAM ALANLARI" slaytının canlı karşılığı).
 *
 * placement: uygulamada reklamın gösterileceği slot
 *   home_banner            → Ana Sayfa Banner (Standart · tüm sektörler)
 *   ride_tracking          → Yolculuk Takip (Platin · esir dikkat anı)
 *   radar_map              → Radar / Harita (Orta segment)
 *   driver_panel           → Sürücü Paneli (gün boyu açık)
 *   sponsored_notification → Sponsorlu Bildirim (push)
 *
 * Her slot süper adminden yönetilir; aktif reklam yoksa sitede "REKLAM ALANINIZ"
 * boş alanı görünür. impressions/clicks süper admin panelinde ölçüm için sayılır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->string('placement', 40)->index()
                ->comment('home_banner | ride_tracking | radar_map | driver_panel | sponsored_notification');
            $table->string('sector', 40)->nullable()
                ->comment('sigorta | otomotiv | insaat_emlak | akaryakit_lastik | banka_finans | yerel | diger');

            $table->string('title');
            $table->string('sponsor_name')->nullable();
            $table->string('description', 500)->nullable();

            $table->string('image_url')->nullable();
            $table->string('link_url')->nullable();
            $table->string('cta_text', 40)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);

            // Yayın penceresi (boşsa süresiz)
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Ölçüm sayaçları
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
