<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('driver_applications', function (Blueprint $table) {
            $table->id();

            $table->string('full_name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('birth_year')->nullable();

            $table->string('license_class', 10)->default('B');
            $table->enum('experience_band', ['under_1', '1_to_3', '3_to_5', '5_plus'])->default('1_to_3');
            $table->boolean('has_src')->default(false);
            $table->boolean('has_vehicle')->default(false);
            $table->string('vehicle_info')->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'contacted', 'approved', 'rejected'])->default('pending');
            $table->string('source', 64)->nullable();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_applications');
    }
};
