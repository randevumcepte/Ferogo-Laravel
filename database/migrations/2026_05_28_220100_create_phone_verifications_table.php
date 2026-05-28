<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS OTP doğrulama. Bir telefon = bir hesap politikası burada.
 * verified_at dolu kayıt = bu telefon doğrulanmış demek.
 * verification_token = doğrulamadan sonra ride_request'e teslim edilen tek-kullanımlık jeton.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32);
            $table->string('code_hash', 100);          // bcrypt
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();

            // Doğrulama başarılıysa: ride request'te kullanılacak tek-seferlik jeton (24 saat)
            $table->string('verification_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_used_at')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('fingerprint', 64)->nullable();

            $table->timestamps();

            $table->index('phone');
            $table->index('verified_at');
            $table->index(['phone', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};
