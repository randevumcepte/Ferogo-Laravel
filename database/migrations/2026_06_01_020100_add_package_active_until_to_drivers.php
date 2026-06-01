<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Aktif paketin bitiş zamanı — dispatch buradan hızlıca check eder.
            // Cache niteliğinde; gerçek kaynak: driver_packages tablosu.
            $table->timestamp('package_active_until')->nullable()->after('rating');
            $table->index('package_active_until');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['package_active_until']);
            $table->dropColumn('package_active_until');
        });
    }
};
