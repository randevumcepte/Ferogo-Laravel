<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * device_tokens — mobil cihazın FCM token'ı + Sanctum token'ına bağlandığı kayıt.
 *
 * Güvenlik amacı:
 *  1) Push gönderebilmek (FCM token tutulur)
 *  2) Token theft tespiti — aynı Sanctum token'ı 2 farklı device_id'den geldiyse şüpheli
 *  3) "Diğer cihazlardan çıkış" özelliği (revoke)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Sanctum token id'sine bağ — token revoke edilirse cascade
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();

            // Cihazın benzersiz kimliği (Flutter tarafında ilk kurulumda üretilir,
            // secure_storage'da saklanır; reset olursa yeni token üretilir)
            $table->string('device_id', 64)->index();

            // FCM/APNs push token
            $table->string('fcm_token', 512)->nullable();

            // Cihaz meta
            $table->enum('platform', ['ios', 'android', 'web'])->default('android');
            $table->string('app_version', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('device_model', 64)->nullable();
            $table->string('locale', 8)->nullable();

            // Audit
            $table->ipAddress('last_ip')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            // Aynı kullanıcının aynı cihaz id'si ile tek satırı olur
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
