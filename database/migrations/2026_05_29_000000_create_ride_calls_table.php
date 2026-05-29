<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ride_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_request_id')->constrained('ride_requests')->cascadeOnDelete();
            $table->enum('initiator', ['customer', 'driver']);
            $table->enum('status', ['ringing', 'accepted', 'rejected', 'ended', 'missed'])->default('ringing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['ride_request_id', 'status']);
        });

        Schema::create('call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_call_id')->constrained('ride_calls')->cascadeOnDelete();
            $table->enum('from_role', ['customer', 'driver']);
            $table->enum('type', ['offer', 'answer', 'ice', 'bye']);
            $table->json('payload');
            $table->boolean('consumed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ride_call_id', 'consumed', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_signals');
        Schema::dropIfExists('ride_calls');
    }
};
