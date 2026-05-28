<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ride requests'e koruma kolonları:
 * - OTP doğrulama izi
 * - IP + cihaz fingerprint (rate limit / forensic)
 * - Sürücü varış + müşteri onay zaman damgaları (no-show akışı)
 * - 'no_show' statüsü enum'a eklenir
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // Doğrulama
            $table->timestamp('phone_verified_at')->nullable()->after('customer_phone');
            $table->string('verification_token', 64)->nullable()->after('phone_verified_at');

            // Forensic
            $table->string('client_ip', 45)->nullable()->after('verification_token');
            $table->string('client_fingerprint', 64)->nullable()->after('client_ip');
            $table->string('user_agent', 500)->nullable()->after('client_fingerprint');

            // No-show akışı
            $table->timestamp('driver_arrived_at')->nullable()->after('accepted_at');
            $table->timestamp('customer_confirmed_at')->nullable()->after('driver_arrived_at');
            $table->timestamp('no_show_at')->nullable()->after('customer_confirmed_at');

            // Captcha — yeni ya da düşük güvenilirlik durumlarında
            $table->boolean('captcha_passed')->default(false)->after('no_show_at');

            $table->index('client_ip');
            $table->index('client_fingerprint');
        });

        // status enum'a 'no_show' ekle (DBAL gerekmeden raw query)
        \DB::statement("ALTER TABLE ride_requests MODIFY COLUMN status ENUM(
            'pending', 'accepted', 'exhausted', 'cancelled', 'no_show'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        \DB::statement("ALTER TABLE ride_requests MODIFY COLUMN status ENUM(
            'pending', 'accepted', 'exhausted', 'cancelled'
        ) NOT NULL DEFAULT 'pending'");

        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropIndex(['client_ip']);
            $table->dropIndex(['client_fingerprint']);
            $table->dropColumn([
                'phone_verified_at',
                'verification_token',
                'client_ip',
                'client_fingerprint',
                'user_agent',
                'driver_arrived_at',
                'customer_confirmed_at',
                'no_show_at',
                'captcha_passed',
            ]);
        });
    }
};
