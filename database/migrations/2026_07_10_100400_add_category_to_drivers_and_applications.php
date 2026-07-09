<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * driver_applications ve drivers tablolarına kategori + marka/model FK'ları
     * ekle. Eski kayıtlar için nullable — hepsi 'otomobil' varsayılan olarak
     * seed'de doldurulur.
     */
    public function up(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->foreignId('driver_category_id')
                ->nullable()
                ->after('license_class')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('vehicle_make_id')
                ->nullable()
                ->after('vehicle_info')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('vehicle_model_id')
                ->nullable()
                ->after('vehicle_make_id')
                ->constrained()
                ->nullOnDelete();
            $table->unsignedSmallInteger('vehicle_year')
                ->nullable()
                ->after('vehicle_model_id');
            $table->string('vehicle_color', 30)
                ->nullable()
                ->after('vehicle_year');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('driver_category_id')
                ->nullable()
                ->after('license_class')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['driver_category_id']);
            $table->dropColumn('driver_category_id');
        });

        Schema::table('driver_applications', function (Blueprint $table) {
            $table->dropForeign(['driver_category_id']);
            $table->dropForeign(['vehicle_make_id']);
            $table->dropForeign(['vehicle_model_id']);
            $table->dropColumn([
                'driver_category_id',
                'vehicle_make_id',
                'vehicle_model_id',
                'vehicle_year',
                'vehicle_color',
            ]);
        });
    }
};
