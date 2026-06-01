<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Güvenlik olayları (security incident) — çağrı merkezi tarafından izlenip
 * yönetilen olaylar:
 *
 *  - 'visual_mismatch' : Müşteri "araç/sürücü resmi tutmuyor" dedi
 *  - 'wrong_vehicle'   : Sürücü farklı bir araçla geldi
 *  - 'wrong_driver'    : Sürücü kimliği uyumsuz
 *  - 'driver_no_show'  : Sürücü konuma gelmedi
 *  - 'customer_no_show': Müşteri yerinde değil
 *  - 'safety_concern'  : Yolcu/sürücü kendini güvende hissetmiyor
 *  - 'panic_button'    : Acil yardım butonu basıldı (panic_alerts ile ilişkili)
 *  - 'other'           : Manuel açılan vaka
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique()->comment('ULID — admin link için');

            // Hangi yolculuğa bağlı (panic'te ride'sız olabilir)
            $table->foreignId('ride_request_id')->nullable()->constrained('ride_requests')->nullOnDelete();
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Olay tipi + kim ihbar etti
            $table->string('type', 32)->index()
                ->comment('visual_mismatch | wrong_vehicle | wrong_driver | driver_no_show | customer_no_show | safety_concern | panic_button | other');
            $table->string('reported_by', 16)
                ->comment('customer | driver | system | operator');
            $table->text('reporter_note')->nullable()->comment('İhbar eden notu (varsa)');

            // Durum
            $table->string('status', 16)->default('open')->index()
                ->comment('open | investigating | resolved_ok | resolved_suspended | escalated_police');
            $table->string('severity', 8)->default('high')->index()
                ->comment('low | medium | high | critical');

            // Operatör müdahalesi
            $table->foreignId('handler_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            // Snapshot — olay anında konum
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
