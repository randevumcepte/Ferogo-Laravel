<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('current_vehicle_id')->nullable();

            // Belgeler
            $table->string('license_class', 10)->default('B');
            $table->date('license_issued_at')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->string('src_certificate_number')->nullable();
            $table->date('src_expires_at')->nullable();
            $table->date('psychotechnic_test_at')->nullable();
            $table->date('criminal_record_at')->nullable();
            $table->enum('experience_band', ['under_1', '1_to_3', '3_to_5', '5_plus'])->default('1_to_3');

            // Komisyon (varsayılan platform %15 → sürücü %85)
            $table->decimal('commission_rate', 5, 2)->default(15.00);

            // Durum
            $table->enum('availability_status', ['offline', 'online', 'busy'])->default('offline');
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_updated_at')->nullable();

            // Onay
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Performans
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->unsignedInteger('total_rides')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'availability_status']);
            $table->index(['current_lat', 'current_lng']);
            $table->index('approval_status');
        });

        // current_vehicle_id FK ayrı (circular dep önlemek için)
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreign('current_vehicle_id')
                  ->references('id')->on('vehicles')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['current_vehicle_id']);
        });
        Schema::dropIfExists('drivers');
    }
};
