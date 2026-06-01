<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hukuki onayların delil niteliğinde audit log'u.
 *
 * Her "Anladım, devam et" / KVKK checkbox / form gönderimi burada
 * kayıt altına alınır. İleride bir kullanıcı "ben bunu görmedim" derse
 * mahkemede ispat için kullanılır.
 *
 * Kayıt asla silinmez (legal retention).
 *
 * - text_version_id ile o anki metnin TAM içeriğine link verilir.
 * - sha256_snapshot ile o anki içeriğin hash'i denormalize edilir
 *   (tablo bağımsız ispat kolaylığı için).
 * - phone null olabilir (anonim kabul); OTP doğrulandığında geriye
 *   dönük doldurulur (LegalConsentService::identifyByPhone).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_consents', function (Blueprint $table) {
            $table->id();

            // ─── Kimlik (üçü de nullable, en az biri olur) ───
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('device_fingerprint', 64)->nullable()->index();

            // ─── Hangi metin / hangi versiyon ───
            $table->foreignId('text_version_id')->constrained('legal_text_versions')->cascadeOnDelete();
            $table->string('text_key_snapshot', 64)->index();
            $table->string('version_snapshot', 64);
            $table->char('sha256_snapshot', 64);

            // ─── Onay metadata ───
            $table->timestamp('accepted_at')->useCurrent()->index();
            $table->string('accepted_via', 32)->comment('modal | checkbox | sms_otp | driver_registration | reservation');
            $table->string('consent_type', 64)->index()->comment('platform_notice, terms, kvkk, distance_sales, ride_sharing, driver_registration, reservation_kvkk');

            // ─── Forensik ───
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('request_url', 500)->nullable();
            $table->string('referer', 500)->nullable();

            // ─── Hammadde (tüm orijinal POST payload — yedek) ───
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            // Hızlı sorgular
            $table->index(['phone', 'accepted_at']);
            $table->index(['consent_type', 'accepted_at']);
            $table->index(['ip_address', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_consents');
    }
};
