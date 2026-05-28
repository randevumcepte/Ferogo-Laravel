<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müşteri güven katmanına göre değişen "indi-bindi" (boarding_fee) ücretleri.
 *
 * 4 katman:
 *   - trusted    (güvenilir, 5+ tamamlanmış)         → düşük indi-bindi (örn. 99 ₺)
 *   - standard   (doğrulanmış, normal)               → orta (örn. 150 ₺)
 *   - new        (yeni hesap, doğrulanmamış)         → yüksek (örn. 210 ₺)
 *   - suspicious (geçmişte no-show, riskli)          → caydırıcı (örn. 350 ₺)
 *
 * Tarife önceliği FareCalculator'de aynen mevcut akışı izler:
 *   PricingRule (city × vehicle_class) → varsa onun değerleri,
 *   yoksa VehicleClass varsayılanları.
 *
 * `rides` tablosunda her yolculukta o anda çözümlenen indi-bindi tutarı ve
 * müşterinin hangi katmanda olduğu saklanır (denetim ve şeffaflık için).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_classes', function (Blueprint $table) {
            $table->decimal('boarding_fee_trusted', 8, 2)->default(99.00)->after('minimum_fare');
            $table->decimal('boarding_fee_standard', 8, 2)->default(150.00)->after('boarding_fee_trusted');
            $table->decimal('boarding_fee_new', 8, 2)->default(210.00)->after('boarding_fee_standard');
            $table->decimal('boarding_fee_suspicious', 8, 2)->default(350.00)->after('boarding_fee_new');
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            // Şehir bazlı override (nullable; null ise vehicle_classes değerleri kullanılır)
            $table->decimal('boarding_fee_trusted', 8, 2)->nullable()->after('minimum_fare');
            $table->decimal('boarding_fee_standard', 8, 2)->nullable()->after('boarding_fee_trusted');
            $table->decimal('boarding_fee_new', 8, 2)->nullable()->after('boarding_fee_standard');
            $table->decimal('boarding_fee_suspicious', 8, 2)->nullable()->after('boarding_fee_new');
        });

        Schema::table('rides', function (Blueprint $table) {
            // Yolculuk anında uygulanan indi-bindi
            $table->decimal('boarding_fee', 8, 2)->default(0)->after('base_fare');
            // Müşterinin o anki güven katmanı (trusted/standard/new/suspicious)
            $table->string('customer_trust_tier', 16)->nullable()->after('boarding_fee');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['boarding_fee', 'customer_trust_tier']);
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn([
                'boarding_fee_trusted',
                'boarding_fee_standard',
                'boarding_fee_new',
                'boarding_fee_suspicious',
            ]);
        });

        Schema::table('vehicle_classes', function (Blueprint $table) {
            $table->dropColumn([
                'boarding_fee_trusted',
                'boarding_fee_standard',
                'boarding_fee_new',
                'boarding_fee_suspicious',
            ]);
        });
    }
};
