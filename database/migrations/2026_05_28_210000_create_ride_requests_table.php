<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            // Müşteri
            $table->string('customer_name');
            $table->string('customer_phone', 32);

            // Yolculuk
            $table->foreignId('vehicle_class_id')->constrained()->restrictOnDelete();
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('estimated_fare', 10, 2)->nullable();

            // State machine
            // pending: bir sürücüye teklif edildi, cevap bekleniyor
            // accepted: bir sürücü kabul etti, Ride yaratıldı (ride_id dolu)
            // exhausted: tüm aday sürücüler red/timeout — kimse almadı
            // cancelled: müşteri vazgeçti
            $table->enum('status', ['pending', 'accepted', 'exhausted', 'cancelled'])
                  ->default('pending');

            // Aday kuyruğu — ordered driver IDs
            // [seçilen, fallback1, fallback2, ...] — sıralı denenir
            $table->json('candidate_driver_ids')->nullable();
            $table->unsignedTinyInteger('current_candidate_index')->default(0);

            // Şu an teklifte olan sürücü + teklif son geçerlilik
            $table->foreignId('offered_driver_id')->nullable()
                  ->constrained('drivers')->nullOnDelete();
            $table->timestamp('offer_expires_at')->nullable();

            // Kabul edildiğinde
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_driver_id')->nullable()
                  ->constrained('drivers')->nullOnDelete();
            $table->foreignId('ride_id')->nullable()
                  ->constrained('rides')->nullOnDelete();

            $table->unsignedSmallInteger('rejection_count')->default(0);

            $table->timestamps();

            $table->index(['offered_driver_id', 'status']);
            $table->index(['status', 'offer_expires_at']);
            $table->index('accepted_driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
