<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rides')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `rides` MODIFY `source` ENUM('app', 'web', 'call', 'whatsapp', 'radar_quick') NOT NULL DEFAULT 'web'");
        }
        // SQLite/PostgreSQL'de ENUM kısıtı CHECK üzerinden gelir; mevcut migration
        // SQLite kullanırken zaten daha esnek davranır, bu yüzden no-op geçilebilir.
    }

    public function down(): void
    {
        if (! Schema::hasTable('rides')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // 'radar_quick' kayıtları varsa onları 'web' olarak normalize et
            DB::statement("UPDATE `rides` SET `source` = 'web' WHERE `source` = 'radar_quick'");
            DB::statement("ALTER TABLE `rides` MODIFY `source` ENUM('app', 'web', 'call', 'whatsapp') NOT NULL DEFAULT 'web'");
        }
    }
};
