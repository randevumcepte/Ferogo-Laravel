<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiyat pazarlığı (inDrive tarzı) katmanı.
 *
 * Akış (hibrit):
 *   1. Yolcu sürücü seçer → sistem suggested_fare (ortalama/çapa) üretir.
 *      Yolcu +/- ile customer_offer_fare belirler (band: suggested ±%40).
 *   2. Seçilen sürücü 1:1: OK / counter (driver_counter_fare) / ret.
 *      - Counter → negotiation_state=driver_countered, top yolcuda.
 *      - Yolcu: kabul / tekrar counter / vazgeç (max NEGOTIATION_ROUNDS tur).
 *   3. Anlaşma → agreed_fare set → Ride.total_fare = agreed_fare, yolculuk başlar.
 *   4. Anlaşma olmazsa mevcut pool-expand devreye girer; yolcunun son teklifi
 *      havuza taşınır, pool sürücüsü kabul/counter eder, yolcu son onay verir.
 *
 * ride_price_offers: her adımın denetim/uyuşmazlık kaydı (KVKK + destek).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Sistemin önerdiği ortalama fiyat (çapa) — bağlayıcı değil, bilgi amaçlı
            $table->decimal('suggested_fare', 10, 2)->nullable()->after('estimated_fare')
                ->comment('Sistem önerisi (ortalama) — pazarlık çapası, bağlayıcı değil');
            // Yolcunun o an masadaki teklifi
            $table->decimal('customer_offer_fare', 10, 2)->nullable()->after('suggested_fare')
                ->comment('Yolcunun güncel teklif ettiği ücret');
            // Sürücünün karşı teklifi
            $table->decimal('driver_counter_fare', 10, 2)->nullable()->after('customer_offer_fare')
                ->comment('Sürücünün karşı teklif ettiği ücret');
            // Anlaşılan nihai ücret (Ride.total_fare buraya eşitlenir)
            $table->decimal('agreed_fare', 10, 2)->nullable()->after('driver_counter_fare')
                ->comment('İki tarafın anlaştığı nihai ücret');
            // Pazarlık durumu: customer_offered | driver_countered | agreed
            $table->string('negotiation_state', 32)->nullable()->after('agreed_fare')
                ->comment('customer_offered | driver_countered | agreed');
            // Tur sayacı (kötüye kullanım + tur limiti)
            $table->unsignedTinyInteger('negotiation_round')->default(0)->after('negotiation_state');
        });

        Schema::create('ride_price_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_request_id')->constrained('ride_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('driver_id')->nullable()->index();
            // Kim teklif etti: customer | driver | system
            $table->string('actor', 16);
            // Adım türü: offer | counter | accept | reject
            $table->string('type', 16);
            $table->decimal('amount', 10, 2)->nullable();
            $table->unsignedTinyInteger('round')->default(0);
            $table->timestamps();

            $table->index(['ride_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_price_offers');

        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropColumn([
                'suggested_fare',
                'customer_offer_fare',
                'driver_counter_fare',
                'agreed_fare',
                'negotiation_state',
                'negotiation_round',
            ]);
        });
    }
};
