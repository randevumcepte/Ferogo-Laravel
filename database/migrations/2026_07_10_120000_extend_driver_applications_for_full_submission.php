<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * driver_applications tablosunu Martı Tag benzeri TEK-FORM başvuruya uygun hale
 * getir. Sürücü, araç ve tüm belgeler tek submit'te alınıp burada saklanır.
 * Admin başvuruyu inceleyip onaylayınca User + Driver + Vehicle kayıtları
 * bu tablodan üretilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            // Ön kayıt sırasında oluşturulan şifre (kullanıcı /surucu-giris'te
            // bu şifre ile giriş yapacak). Onay sonrası User'a aktarılır.
            $table->string('password_hash', 255)->nullable()->after('email');

            // Kimlik doğrulama fotoğrafları
            $table->string('selfie_file_path')->nullable();          // Sürücü selfie
            $table->string('id_front_file_path')->nullable();        // T.C. Kimlik ön
            $table->string('id_back_file_path')->nullable();         // T.C. Kimlik arka

            // Ehliyet
            $table->string('license_front_file_path')->nullable();
            $table->string('license_back_file_path')->nullable();

            // Araç bilgileri
            $table->string('vehicle_plate', 15)->nullable();
            $table->json('vehicle_photos')->nullable();              // 6 açı: front/back/left/right/interior_front/interior_back
            $table->string('registration_file_path')->nullable();    // Ruhsat
            $table->string('insurance_file_path')->nullable();       // Sigorta
            $table->string('inspection_file_path')->nullable();      // Muayene

            // Ortak belgeler
            $table->string('criminal_record_file_path')->nullable(); // Adli sicil

            // Kategori-özel belgeler
            $table->string('src_file_path')->nullable();             // SRC-2 (sarı taksi)
            $table->string('taksi_plaka_file_path')->nullable();     // Ticari taksi plaka izin
            $table->string('taksimetre_file_path')->nullable();      // Taksimetre kalibrasyon
            $table->string('oda_kaydi_file_path')->nullable();       // İzmir Taksiciler Odası kaydı
            $table->string('psychotechnic_file_path')->nullable();   // Psikoteknik
            $table->string('helmet_file_path')->nullable();          // Kask (motosiklet)

            // İnceleme durumu
            $table->timestamp('submitted_at')->nullable();           // Tüm dosyalar tamam ve gönderildi
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'password_hash',
                'selfie_file_path',
                'id_front_file_path',
                'id_back_file_path',
                'license_front_file_path',
                'license_back_file_path',
                'vehicle_plate',
                'vehicle_photos',
                'registration_file_path',
                'insurance_file_path',
                'inspection_file_path',
                'criminal_record_file_path',
                'src_file_path',
                'taksi_plaka_file_path',
                'taksimetre_file_path',
                'oda_kaydi_file_path',
                'psychotechnic_file_path',
                'helmet_file_path',
                'submitted_at',
                'reviewed_at',
                'reviewed_by',
                'rejection_reason',
            ]);
        });
    }
};
