<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 1 — Karşılama (uçak/tren/otogar) bağlantısı.
 *
 * API bağımlılığı YOK: yolcu planlanan varış saatini elle girer, ücretsiz
 * bekleme (tampon) süresi ulaşım tipine göre atanır ve yolcu onay sayfasından
 * şoföre canlı durum sinyali gönderir (yola çıktım / geldim / gecikeceğim).
 * Faz 2'de uçuş no ile otomatik rötar takibi buraya bağlanacak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // flight | train | bus | null (=normal yolculuk)
            $table->string('transport_type', 10)->nullable()->after('dropoff_notes');
            // Uçuş no / sefer no / peron-firma bilgisi (serbest metin)
            $table->string('transport_code', 40)->nullable()->after('transport_type');
            // Yolcunun girdiği planlanan varış saati (iniş / gar-otogar varışı)
            $table->dateTime('transport_scheduled_at')->nullable()->after('transport_code');
            // Ücretsiz bekleme (tampon) süresi — tipe göre atanır, dakika
            $table->unsignedSmallInteger('free_wait_minutes')->nullable()->after('transport_scheduled_at');

            // Yolcu → şoför canlı sinyali: on_way | arrived | delayed | null
            $table->string('pax_status', 20)->nullable()->after('free_wait_minutes');
            $table->string('pax_status_note', 120)->nullable()->after('pax_status');
            $table->dateTime('pax_status_at')->nullable()->after('pax_status_note');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'transport_type',
                'transport_code',
                'transport_scheduled_at',
                'free_wait_minutes',
                'pax_status',
                'pax_status_note',
                'pax_status_at',
            ]);
        });
    }
};
