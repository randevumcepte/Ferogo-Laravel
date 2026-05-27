<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();

            // Pickup
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_notes')->nullable();

            // Dropoff
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->string('dropoff_notes')->nullable();

            // Trip details
            $table->decimal('estimated_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->decimal('actual_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->unsignedTinyInteger('passenger_count')->default(1);
            $table->unsignedTinyInteger('luggage_count')->default(0);

            // Pricing breakdown
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('distance_fare', 10, 2)->default(0);
            $table->decimal('time_fare', 10, 2)->default(0);
            $table->decimal('extras_total', 10, 2)->default(0);
            $table->decimal('multiplier', 4, 2)->default(1.00);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_fare', 10, 2)->default(0);
            $table->string('currency', 3)->default('TRY');

            // Status & timeline
            $table->enum('status', [
                'draft', 'pending', 'searching', 'assigned',
                'driver_arriving', 'in_progress', 'completed',
                'cancelled', 'no_show',
            ])->default('pending');
            $table->enum('source', ['app', 'web', 'call', 'whatsapp'])->default('web');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('driver_arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            // Müşteri bilgisi (5682 + KVKK için zorunlu)
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_tc_no', 11)->nullable();

            // Rating
            $table->unsignedTinyInteger('customer_rating')->nullable();
            $table->text('customer_review')->nullable();
            $table->unsignedTinyInteger('driver_rating')->nullable();
            $table->text('driver_review')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index('scheduled_at');
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
