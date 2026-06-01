<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('driver_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();

            // Paket
            $table->string('type', 20);                       // hourly_3 | daily | weekly | monthly
            $table->unsignedInteger('duration_hours');        // 3 / 24 / 168 / 720
            $table->decimal('price', 10, 2);                  // snapshot — sonradan fiyat değişse de bu kayıt sabit

            // Aktivasyon
            $table->timestamp('starts_at')->nullable();       // ödeme onayında set
            $table->timestamp('expires_at')->nullable();      // starts_at + duration_hours
            $table->enum('status', ['pending', 'active', 'expired', 'failed', 'refunded'])
                  ->default('pending');

            // Ödeme
            $table->string('payment_provider', 30)->default('iyzico'); // iyzico | mock | paytr
            $table->string('payment_reference')->nullable();           // iyzico paymentId / conversationId
            $table->json('payment_meta')->nullable();                  // gateway response snapshot
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index('expires_at');
            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_packages');
    }
};
