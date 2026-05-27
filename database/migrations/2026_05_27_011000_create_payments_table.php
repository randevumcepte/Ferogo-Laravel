<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ride_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('TRY');

            $table->enum('status', [
                'pending', 'authorized', 'captured',
                'failed', 'refunded', 'cancelled',
            ])->default('pending');

            $table->enum('provider', ['iyzico', 'cash', 'card_on_arrival'])->default('iyzico');
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_token')->nullable();
            $table->json('provider_response')->nullable();

            $table->string('card_last_4', 4)->nullable();
            $table->string('card_brand', 32)->nullable();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('provider_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
