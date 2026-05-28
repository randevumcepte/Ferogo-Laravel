<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sürücüye verilen tazminatların hesabı. No-show kanıtlandığında otomatik kaydedilir.
 * status=pending → admin onayıyla ya da otomatik kuyrukla ödeme yapılır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('driver_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('no_show_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ride_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('reason', ['no_show', 'customer_cancel_late', 'manual'])->default('no_show');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('TRY');

            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_compensations');
    }
};
