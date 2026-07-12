<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uygulama içi bildirim kutusu (inbox).
 *
 * Hem işlemsel bildirimler (yeni teklif, sürücü kabul etti, yeni mesaj) hem de
 * kampanya bildirimleri buraya yazılır — kullanıcı "Bildirimler" ekranında geçmişi
 * görür, okundu/okunmadı takibi yapılır. Push gönderimi bundan bağımsızdır
 * (push düşmese bile kayıt burada durur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ride_offer | ride_accepted | ride_arrived | ride_cancelled | message
            // | announcement | promo | info
            $table->string('type', 30)->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('image_url')->nullable();
            $table->string('deep_link')->nullable();
            $table->json('data')->nullable(); // {public_id, ride_request_id, ...} → deep-link için

            $table->foreignId('notification_campaign_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
