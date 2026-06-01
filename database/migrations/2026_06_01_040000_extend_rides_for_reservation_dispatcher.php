<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rezervasyon dispatcher (planlı yolculuk):
 *
 *   reservation_pending_pool       → pool'da, sürücü aranıyor
 *   reservation_accepted           → sürücü kabul etti (>24h ise burada bekler)
 *   reservation_reconfirm_requested→ T-24h, sürücüden teyit istendi
 *   reservation_confirmed          → sürücü teyit etti, müşteriye haber gitti
 *   reservation_imminent           → T-2h, hatırlatma + maskeli arama açıldı
 *   reservation_unmatched          → 12 saat kimse almadı, müşteriye iade
 *
 * Pickup zamanından sonra in_progress / completed / cancelled / no_show
 * mevcut state machine'e devreder.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) ENUM'a yeni status değerlerini ekle (MySQL: MODIFY COLUMN)
        DB::statement("ALTER TABLE `rides` MODIFY COLUMN `status` ENUM(
            'draft','pending','searching','assigned',
            'driver_arriving','in_progress','completed',
            'cancelled','no_show',
            'reservation_pending_pool',
            'reservation_accepted',
            'reservation_reconfirm_requested',
            'reservation_confirmed',
            'reservation_imminent',
            'reservation_unmatched'
        ) NOT NULL DEFAULT 'pending'");

        // 2) Rezervasyon dispatcher kolonları
        Schema::table('rides', function (Blueprint $table) {
            // Pool publish — havuza ne zaman düştü
            $table->timestamp('pool_published_at')->nullable()->after('scheduled_at')
                ->comment('Rezervasyon havuza ne zaman düştü');

            // Kabul aşaması (driver_id ana FK; bu alanlar "ilk kabul eden" izini tutar)
            $table->timestamp('accepted_at')->nullable()->after('pool_published_at')
                ->comment('Sürücü rezervasyonu pool\'dan kabul ettiği an');

            // Pool'dan reddedip geri atılanların geçmişi (çakışma, vazgeçme vb.)
            $table->json('rejected_driver_ids')->nullable()->after('accepted_at')
                ->comment('Pool\'a geri atılma geçmişi (sürücü iptal etti / reconfirm fail)');

            // T-24h reconfirm akışı
            $table->timestamp('reconfirm_requested_at')->nullable()->after('rejected_driver_ids')
                ->comment('T-24h: sürücüye teyit isteği push edildi');
            $table->timestamp('reconfirm_deadline_at')->nullable()->after('reconfirm_requested_at')
                ->comment('Sürücü bu zamana kadar teyit etmezse otomatik geri pool');
            $table->timestamp('driver_reconfirmed_at')->nullable()->after('reconfirm_deadline_at')
                ->comment('Sürücü teyit ettiği an');

            // T-2h imminent
            $table->timestamp('imminent_notified_at')->nullable()->after('driver_reconfirmed_at')
                ->comment('T-2h hatırlatma push\'u gönderildi');
            $table->timestamp('masked_call_unlocked_at')->nullable()->after('imminent_notified_at')
                ->comment('Bu andan itibaren sürücü maskeli arama yapabilir');

            // Ön provizyon (iyzico saved card)
            $table->boolean('prepayment_authorized')->default(false)->after('masked_call_unlocked_at')
                ->comment('Rezervasyon oluşturulurken kartta tutulan provizyon');
            $table->unsignedBigInteger('prepayment_payment_id')->nullable()->after('prepayment_authorized');

            // Index'ler — cron tarama hızı için
            $table->index(['status', 'scheduled_at']);
            $table->index(['status', 'reconfirm_deadline_at']);
            $table->index(['status', 'pool_published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['status', 'reconfirm_deadline_at']);
            $table->dropIndex(['status', 'pool_published_at']);
            $table->dropColumn([
                'pool_published_at',
                'accepted_at',
                'rejected_driver_ids',
                'reconfirm_requested_at',
                'reconfirm_deadline_at',
                'driver_reconfirmed_at',
                'imminent_notified_at',
                'masked_call_unlocked_at',
                'prepayment_authorized',
                'prepayment_payment_id',
            ]);
        });

        DB::statement("ALTER TABLE `rides` MODIFY COLUMN `status` ENUM(
            'draft','pending','searching','assigned',
            'driver_arriving','in_progress','completed',
            'cancelled','no_show'
        ) NOT NULL DEFAULT 'pending'");
    }
};
