<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_favorite_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->timestamps();

            // Aynı müşteri aynı sürücüyü iki kez favoriye ekleyemez.
            $table->unique(['user_id', 'driver_id']);
            // "Bu sürücüyü kaç müşteri favoriledi" + temizleme sorguları için.
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_favorite_drivers');
    }
};
