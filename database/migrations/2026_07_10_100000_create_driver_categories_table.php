<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Sürücü kategorileri: Otomobil, Sarı Taksi, Motosiklet.
     *
     * Bu tablo, MEVCUT vehicle_classes (easy/platinum/vip = premium hizmet
     * katmanları) ile karıştırılmamalı. Bu farklı bir eksen: TAŞIT TÜRÜ.
     * Yasal olarak her taşıt türünün ehliyet sınıfı ve belge listesi farklı.
     */
    public function up(): void
    {
        Schema::create('driver_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();               // otomobil, sari_taksi, motosiklet
            $table->string('name', 80);                          // "Otomobil", "Sarı Taksi", "Motosiklet"
            $table->string('emoji', 8)->nullable();              // 🚗 🚕 🏍
            $table->text('description')->nullable();             // kategori kısa açıklama
            $table->string('required_license_class', 10);        // B, A2, D
            $table->boolean('requires_src')->default(false);     // Sarı taksi için true
            $table->boolean('requires_helmet')->default(false);  // Motosiklet için true
            $table->json('required_documents')->nullable();      // ['license','ruhsat','sigorta',...]
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_categories');
    }
};
