<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_class_id')->constrained()->restrictOnDelete();
            $table->string('brand');
            $table->string('model');
            $table->year('year_of_manufacture');
            $table->string('color');
            $table->string('plate', 20)->unique();
            $table->string('insurance_policy')->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->date('inspection_expires_at')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->boolean('has_baby_seat')->default(false);
            $table->boolean('has_child_seat')->default(false);
            $table->boolean('has_booster_seat')->default(false);
            $table->boolean('pet_friendly')->default(false);
            $table->json('photos')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'retired'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
