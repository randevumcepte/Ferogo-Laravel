<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * iyzico saklı kart (Card Storage) + Masterpass entegrasyonu için altyapı.
 *
 * cardUserKey iyzico'nun kullanıcı anahtarı; bir sürücüye bir kez atanır,
 * altında birden fazla cardToken (her kart için bir token) bulunur.
 *
 * cardUserKey'i `users` tablosunda tutuyoruz çünkü sürücü değişebilir
 * ama altındaki Auth user aynı kalır — kart birikimi de orada anlamlı.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // iyzico cardUserKey — ilk ödeme sonrası iyzico tarafından üretilir,
            // sonraki ödemelerde aynı user'ın kartlarını listelemek için kullanılır.
            $table->string('iyzico_card_user_key', 100)->nullable()->after('avatar');
            $table->index('iyzico_card_user_key');
        });

        Schema::table('driver_packages', function (Blueprint $table) {
            // Bu paket ödenirken hangi kart kullanıldı (saklı kart cardToken).
            // Yeni kart ile ödendiyse callback sırasında iyzico üretir ve kaydederiz.
            $table->string('card_token', 100)->nullable()->after('payment_reference');
            $table->string('card_alias', 50)->nullable()->after('card_token');     // örn. "Garanti İş Kartı"
            $table->string('card_last_four', 4)->nullable()->after('card_alias');  // "1234"

            // 3D Secure akışında iyzico'dan dönen htmlContent ve conversationId
            // Üç saniye gibi kısa ömürlü — ödeme bittiğinde temizlenir.
            $table->text('three_ds_html')->nullable()->after('payment_meta');
            $table->string('conversation_id', 100)->nullable()->after('three_ds_html');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('driver_packages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropColumn(['card_token', 'card_alias', 'card_last_four', 'three_ds_html', 'conversation_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['iyzico_card_user_key']);
            $table->dropColumn('iyzico_card_user_key');
        });
    }
};
