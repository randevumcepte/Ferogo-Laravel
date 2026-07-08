<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sürücü başvuru sayfasındaki ikinci reklam alanı (form altı) kaldırıldı;
 * tek geniş banner (driver_apply) bırakıldı. Eski kayıtları temizle.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('advertisements')->where('placement', 'driver_apply_bottom')->delete();
    }

    public function down(): void
    {
        // Geri alınması gerekmez (alan kaldırıldı).
    }
};
