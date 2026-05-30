<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sürücülerin profilde yaptığı değişiklikler doğrudan canlıya çıkmaz —
 * önce bu tabloya 'pending' olarak yazılır. Süper-admin Filament panelinden
 * onaylarsa gerçek alana uygulanır.
 *
 * Ayrıca drivers tablosuna her belge için '*_approved_at' kolonu eklenir;
 * yüklenen belge bu tarihe kadar 'onay bekliyor' olarak gösterilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('driver_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->enum('type', ['vehicle', 'profile_critical']);
            $table->json('payload');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->timestamp('license_approved_at')->nullable()->after('license_file_path');
            $table->timestamp('src_approved_at')->nullable()->after('src_file_path');
            $table->timestamp('psychotechnic_approved_at')->nullable()->after('psychotechnic_file_path');
            $table->timestamp('criminal_record_approved_at')->nullable()->after('criminal_record_file_path');
            $table->timestamp('insurance_approved_at')->nullable()->after('insurance_file_path');
            $table->timestamp('inspection_approved_at')->nullable()->after('inspection_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'license_approved_at',
                'src_approved_at',
                'psychotechnic_approved_at',
                'criminal_record_approved_at',
                'insurance_approved_at',
                'inspection_approved_at',
            ]);
        });
        Schema::dropIfExists('driver_change_requests');
    }
};
