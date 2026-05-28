<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müşteri güven skoru + ban kaydı.
 * Müşteriler login olmadığı için telefon = kimlik. Her telefon için bir satır.
 * No-show, iptal, tamamlanan yolculuk sayılarıyla score güncellenir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_trust', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->unique();

            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('total_completed')->default(0);
            $table->unsignedInteger('total_customer_cancellations')->default(0);
            $table->unsignedInteger('total_no_shows')->default(0);

            // Son 24 saatteki no-show sayısı — cooldown kararlarında kullanılır
            $table->unsignedSmallInteger('no_shows_24h')->default(0);
            $table->timestamp('no_shows_24h_window_start')->nullable();

            // 0-100 arası güven skoru. 50 başlangıç.
            $table->unsignedTinyInteger('trust_score')->default(50);

            // Geçici cooldown
            $table->timestamp('banned_until')->nullable();
            $table->string('ban_reason')->nullable();

            // Kalıcı kara liste (manuel admin işareti veya 5+ no-show)
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('blacklisted_at')->nullable();
            $table->string('blacklist_reason')->nullable();

            // Son etkinlik
            $table->timestamp('last_request_at')->nullable();
            $table->timestamp('last_no_show_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();

            // Son görülen IP/cihaz (forensic)
            $table->string('last_ip', 45)->nullable();
            $table->string('last_fingerprint', 64)->nullable();

            $table->timestamps();

            $table->index('trust_score');
            $table->index('banned_until');
            $table->index('is_blacklisted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_trust');
    }
};
