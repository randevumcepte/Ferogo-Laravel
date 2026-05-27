<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->index();        // easy, platinum, vip
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('max_passengers')->default(4);
            $table->unsignedTinyInteger('max_luggage')->default(3);
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('per_km_fare', 10, 2)->default(0);
            $table->decimal('per_minute_fare', 10, 2)->default(0);
            $table->decimal('minimum_fare', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_classes');
    }
};
