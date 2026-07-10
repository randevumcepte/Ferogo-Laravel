<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ride_requests.status ENUM → VARCHAR(30).
 *
 * Kod tarafı çoktan çok daha fazla durum kullanıyor:
 *   pending, accepted, exhausted, cancelled, no_show,
 *   pool_expanded, awaiting_customer_reconfirm, driver_arrived,
 *   customer_boarded, in_progress, visual_verified,
 *   completed, suspended_by_incident, ...
 *
 * Yeni durum eklerken bir daha ALTER TABLE yazmayalım — string tut,
 * doğrulama uygulama katmanında.
 *
 * Log'daki hata:
 *   SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status'
 *   → status='pool_expanded' ENUM'a uymuyordu.
 */
return new class extends Migration {
    public function up(): void
    {
        // MySQL: ENUM'dan VARCHAR'a dönüşüm, mevcut değerleri korur.
        DB::statement("ALTER TABLE ride_requests MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");

        // İyi olsun diye INDEX'i de değişik değerleri kavrayacak şekilde bırak.
        // (Zaten var, sadece belirtmek için.)
    }

    public function down(): void
    {
        // Geriye alırken uygulama tarafında geçerli değerlerin hepsini ENUM'a al.
        // Yoksa 'Data truncated' aynı sorun tekrar patlar.
        DB::statement("ALTER TABLE ride_requests MODIFY COLUMN status ENUM(
            'pending','accepted','exhausted','cancelled','no_show',
            'pool_expanded','awaiting_customer_reconfirm',
            'driver_arrived','customer_boarded','in_progress',
            'visual_verified','completed','suspended_by_incident'
        ) NOT NULL DEFAULT 'pending'");
    }
};
