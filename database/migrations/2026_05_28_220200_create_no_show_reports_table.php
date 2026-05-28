<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sürücünün "müşteri gelmedi" raporu. Audit trail + tazminat tetikleyici.
 * Her bir no-show olayı buraya satır olarak düşer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('no_show_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ride_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();

            $table->string('customer_phone', 32);

            // Karar
            // pending: yeni rapor, henüz işlenmedi
            // confirmed: sistem onayladı (5+ dk bekleme + GPS yakınlık) → tazminat hak edildi
            // disputed: müşteri itiraz etti
            // dismissed: admin reddetti
            $table->enum('resolution', ['pending', 'confirmed', 'disputed', 'dismissed'])
                  ->default('pending');

            // Sürücü gerçekten beklemiş mi? — GPS doğrulaması
            $table->decimal('reported_lat', 10, 7)->nullable();
            $table->decimal('reported_lng', 10, 7)->nullable();
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->decimal('distance_from_pickup_m', 8, 2)->nullable();
            $table->unsignedSmallInteger('wait_seconds')->nullable();

            // Tazminat
            $table->decimal('compensation_amount', 10, 2)->default(0);
            $table->timestamp('compensation_paid_at')->nullable();

            $table->text('driver_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->index('customer_phone');
            $table->index(['driver_id', 'resolution']);
            $table->index('resolution');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('no_show_reports');
    }
};
