<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acil yardım (panic) butonu kayıtları.
 *
 * Sürücü veya müşteri "ACİL YARDIM" butonuna bastığında oluşur.
 * Çağrı merkezi yüksek öncelikli sıraya düşer; operatör ARAR,
 * gerekirse polis (155) çağrılır.
 *
 * Statüler:
 *  - 'triggered'         : Tetiklendi, henüz operatör görmedi
 *  - 'acknowledged'      : Operatör gördü
 *  - 'contacting'        : Operatör aramaya başladı
 *  - 'police_dispatched' : Polis çağrıldı
 *  - 'resolved'          : Çözüldü, güvende
 *  - 'false_alarm'       : Yanlış basıldı
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('panic_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();

            // Ride bağlantısı (zorunlu değil — ride dışı da olabilir)
            $table->foreignId('ride_request_id')->nullable()->constrained('ride_requests')->nullOnDelete();
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();

            // Tetikleyen
            $table->string('triggered_by_type', 16)->index()->comment('driver | customer');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('triggered_by_phone', 32)->nullable()->index();

            // Konum (kritik)
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('location_accuracy_m', 8, 2)->nullable();

            // Forensik
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_fingerprint', 64)->nullable();

            // Durum
            $table->string('status', 24)->default('triggered')->index();
            $table->string('severity', 8)->default('critical')->index();

            // Operatör müdahalesi
            $table->foreignId('handler_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('police_called_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('operator_notes')->nullable();

            // İlgili security incident'a köprü (gerekirse açılır)
            $table->foreignId('security_incident_id')->nullable()->constrained('security_incidents')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panic_alerts');
    }
};
