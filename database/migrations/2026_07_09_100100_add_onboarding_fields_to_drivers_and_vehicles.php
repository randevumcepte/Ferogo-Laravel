<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hesap-önce (Martı modeli) sürücü onboarding'i için alan eklemeleri.
 *
 * drivers:
 *   - selfie_file_path / selfie_approved_at : yüz doğrulama selfie'si
 *   - submitted_at : sürücü tüm belgeleri tamamlayıp incelemeye gönderdiğinde set edilir
 *                    (null = onboarding devam ediyor / eksik; dolu = inceleme bekliyor)
 *
 * vehicles:
 *   - vehicle_type : "Araç Tipi" (otomobil / minivan / ticari ...) — sınıftan (Easy/VIP) farklı
 *   - vehicle_make_id / vehicle_model_id : SEÇMELİ marka+model (katalog FK)
 *   - registration_file_path / registration_approved_at : ruhsat
 *   - class_confirmed_at : sürücü sınıfı ÖNERİR, admin incelemede ONAYLAR/düzeltir
 *   - photo_angles : 6 açılı yapılandırılmış foto (left/front/right/back/interior_front/interior_back)
 *
 * driver_applications.user_id : ön kayıtta oluşan hesabı başvuruya bağlar (audit).
 *
 * NOT: approval_status enum'una dokunulmadı. Onboarding aşaması submitted_at ile ayrışır:
 *   pending + submitted_at=null   → eksik/devam ediyor
 *   pending + submitted_at dolu   → inceleme bekliyor
 *   approved / rejected           → sonuç
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('selfie_file_path')->nullable()->after('criminal_record_file_path');
            $table->timestamp('selfie_approved_at')->nullable()->after('selfie_file_path');
            $table->timestamp('submitted_at')->nullable()->after('approved_at');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_type', 30)->nullable()->after('vehicle_class_id');
            $table->foreignId('vehicle_make_id')->nullable()->after('vehicle_type')
                  ->constrained('vehicle_makes')->nullOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->after('vehicle_make_id')
                  ->constrained('vehicle_models')->nullOnDelete();
            $table->string('registration_file_path')->nullable()->after('plate');
            $table->timestamp('registration_approved_at')->nullable()->after('registration_file_path');
            $table->timestamp('class_confirmed_at')->nullable()->after('vehicle_class_id');
            $table->json('photo_angles')->nullable()->after('photos');
        });

        Schema::table('driver_applications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['selfie_file_path', 'selfie_approved_at', 'submitted_at']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_make_id');
            $table->dropConstrainedForeignId('vehicle_model_id');
            $table->dropColumn([
                'vehicle_type',
                'registration_file_path',
                'registration_approved_at',
                'class_confirmed_at',
                'photo_angles',
            ]);
        });

        Schema::table('driver_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
