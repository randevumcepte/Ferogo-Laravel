<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Güvenlik olayı sırasında sürücüden alınan zorunlu doğrulama fotoğrafları.
 *
 * 3 tip:
 *  - 'selfie'    : Sürücünün ön kamera özçekimi
 *  - 'vehicle'   : Aracın dış görünüm fotoğrafı
 *  - 'plate'     : Plakanın net fotoğrafı
 *
 * Çağrı merkezi operatörü inceler → status:
 *  - 'pending_review' : Yüklendi, henüz incelenmedi
 *  - 'approved'       : Doğrulandı, sorun yok
 *  - 'rejected'       : Tutarsız, sürücü askıya alınmalı
 *  - 'expired'        : Süresinde yüklenmedi, otomatik askı
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('verification_photos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('security_incident_id')->constrained('security_incidents')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('ride_request_id')->nullable()->constrained('ride_requests')->nullOnDelete();

            $table->string('type', 16)->index()->comment('selfie | vehicle | plate');

            // Dosya
            $table->string('disk', 16)->default('public');
            $table->string('path', 255);
            $table->string('mime_type', 64)->nullable();
            $table->integer('size_bytes')->nullable();

            // Cihaz metadata
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->boolean('flash_used')->default(false)->comment('Gece beyaz ekran flash kullanıldı mı');
            $table->boolean('front_camera')->default(true)->comment('Ön kamera mı arka kamera mı');

            // İnceleme
            $table->string('status', 16)->default('pending_review')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_photos');
    }
};
