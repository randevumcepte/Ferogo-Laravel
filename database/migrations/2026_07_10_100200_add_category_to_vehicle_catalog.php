<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * vehicle_makes ve vehicle_models tablolarına driver kategori bilgisi ekle.
     *
     * Motor markalari (Yamaha, Kawasaki) sadece 'motosiklet' kategorisinde,
     * otomobil markalari (Toyota, Ford) 'otomobil' + 'sari_taksi' kategorisinde
     * görünsün. Bu şekilde başvuru formunda kategori seçince ilgili markalar
     * dropdown'da otomatik filtrelenir.
     */
    public function up(): void
    {
        Schema::table('vehicle_makes', function (Blueprint $table) {
            // JSON dizi: ["otomobil","sari_taksi"] veya ["motosiklet"] vb.
            $table->json('applicable_categories')->nullable()->after('slug');
            $table->string('logo_url')->nullable()->after('applicable_categories');
        });

        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->string('category_slug', 40)->nullable()->after('name');
            $table->unsignedSmallInteger('production_start')->nullable()->after('category_slug');
            $table->unsignedSmallInteger('production_end')->nullable()->after('production_start');

            $table->index(['category_slug', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->dropIndex(['category_slug', 'is_active']);
            $table->dropColumn(['category_slug', 'production_start', 'production_end']);
        });

        Schema::table('vehicle_makes', function (Blueprint $table) {
            $table->dropColumn(['applicable_categories', 'logo_url']);
        });
    }
};
