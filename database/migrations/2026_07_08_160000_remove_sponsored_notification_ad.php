<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Sponsorlu Bildirim" alanı müşteri panelinden kaldırıldı; ilgili örnek kaydı da temizle.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('advertisements')->where('placement', 'sponsored_notification')->delete();
    }

    public function down(): void
    {
        // Geri alınması gerekmez.
    }
};
