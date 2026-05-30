<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sürücülerin yüklediği resmi belge dosyalarını saklamak için path kolonları.
 * Driver'da expires_at gibi metadata zaten vardı; bu migration sadece dosya yollarını ekler.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('license_file_path')->nullable()->after('license_expires_at');
            $table->string('src_file_path')->nullable()->after('src_expires_at');
            $table->string('psychotechnic_file_path')->nullable()->after('psychotechnic_test_at');
            $table->string('criminal_record_file_path')->nullable()->after('criminal_record_at');
            $table->string('insurance_file_path')->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->string('inspection_file_path')->nullable();
            $table->date('inspection_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'license_file_path',
                'src_file_path',
                'psychotechnic_file_path',
                'criminal_record_file_path',
                'insurance_file_path',
                'insurance_expires_at',
                'inspection_file_path',
                'inspection_expires_at',
            ]);
        });
    }
};
