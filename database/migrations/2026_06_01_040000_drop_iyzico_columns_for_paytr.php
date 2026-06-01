<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * iyzico'dan PayTR'a geçiş — iyzico'ya özel kolonları temizle.
 *
 * - users.iyzico_card_user_key: iyzico Card Storage anahtarı, PayTR'da gereksiz
 *   (saklı kart iframe içinde otomatik yönetiliyor)
 * - driver_packages.three_ds_html: iyzico 3D HTML payload'ı, PayTR iframe ile gereksiz
 * - driver_packages.card_token: iyzico cardToken, PayTR'da kullanılmıyor
 *
 * conversation_id, card_alias, card_last_four kalır — PayTR akışında da kullanılıyor
 * (sırasıyla: paytr_token, payment_type, masked_pan son 4).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'iyzico_card_user_key')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['iyzico_card_user_key']);
                $table->dropColumn('iyzico_card_user_key');
            });
        }

        Schema::table('driver_packages', function (Blueprint $table) {
            if (Schema::hasColumn('driver_packages', 'three_ds_html')) {
                $table->dropColumn('three_ds_html');
            }
            if (Schema::hasColumn('driver_packages', 'card_token')) {
                $table->dropColumn('card_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('iyzico_card_user_key', 100)->nullable();
            $table->index('iyzico_card_user_key');
        });

        Schema::table('driver_packages', function (Blueprint $table) {
            $table->text('three_ds_html')->nullable();
            $table->string('card_token', 100)->nullable();
        });
    }
};
