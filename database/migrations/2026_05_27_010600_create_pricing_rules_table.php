<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_class_id')->constrained()->cascadeOnDelete();

            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('per_km_fare', 10, 2)->default(0);
            $table->decimal('per_minute_fare', 10, 2)->default(0);
            $table->decimal('minimum_fare', 10, 2)->default(0);

            // Çarpanlar
            $table->decimal('night_multiplier', 4, 2)->default(1.00);
            $table->time('night_start')->default('22:00:00');
            $table->time('night_end')->default('06:00:00');
            $table->decimal('peak_multiplier', 4, 2)->default(1.00);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city_id', 'vehicle_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
