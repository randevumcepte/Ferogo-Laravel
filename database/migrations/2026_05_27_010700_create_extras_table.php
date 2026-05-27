<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->index();            // baby_seat, pet, premium_package vs.
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('type', ['seat', 'pet', 'package', 'baggage', 'other'])->default('other');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('per_unit')->default(false);
            $table->unsignedTinyInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extras');
    }
};
