<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panik WebRTC sesli görüşme sinyalleşmesi (SDP/ICE kuyruğu).
 *
 * Kişi (sürücü/yolcu) = arayan (offer), destek çalışanı = cevaplayan (answer).
 * Ride call sisteminden bağımsız — panik alarmına bağlı, operatör admin panelde
 * tarayıcı üzerinden (WebRTC) konuşur. Ses P2P, sinyal HTTP polling.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('panic_call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panic_alert_id')->constrained('panic_alerts')->cascadeOnDelete();
            $table->string('from_role', 12);           // user | operator
            $table->string('type', 12);                // offer | answer | ice | bye
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->index(['panic_alert_id', 'from_role', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panic_call_signals');
    }
};
