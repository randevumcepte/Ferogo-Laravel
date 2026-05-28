<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ride_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_request_id')->constrained()->cascadeOnDelete();
            // 'system' = otomatik mesajlar ("Şoför yola çıktı" gibi)
            $table->enum('sender', ['customer', 'driver', 'system']);
            $table->string('body', 1000);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['ride_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_messages');
    }
};
