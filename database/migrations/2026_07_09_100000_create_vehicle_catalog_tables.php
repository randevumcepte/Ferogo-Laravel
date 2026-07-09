<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Araç marka/model kataloğu — sürücü onboarding'inde marka+model SEÇMELİ (dropdown) olsun diye.
 * Serbest metin yerine sabit liste: veri tutarlılığı + filtreleme + admin denetimi kolaylaşır.
 *
 * vehicle_makes  → markalar (Renault, Volkswagen, ...)
 * vehicle_models → her markanın modelleri (Clio, Passat, ...)
 *
 * Seed: VehicleCatalogSeeder (Türkiye pazarındaki yaygın markalar/modeller).
 * Liste admin panelinden genişletilebilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_make_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vehicle_make_id', 'name']);
            $table->index(['vehicle_make_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_makes');
    }
};
