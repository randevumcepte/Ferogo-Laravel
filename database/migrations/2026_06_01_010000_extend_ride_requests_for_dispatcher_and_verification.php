<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ride request lifecycle'ını yeni güvenlik mimarisine taşır:
 *
 *  pending
 *    └─ (30 sn cevapsız) → pool_expanding (havuza yayıldı)
 *    └─ accepted_by_first → awaiting_customer_reconfirm (fallback sürücü kabul etti, müşteri onay versin)
 *    └─ accepted (müşteri başlangıçta seçtiği sürücüyü ya da fallback sürücüyü onayladı)
 *    └─ driver_arrived
 *    └─ customer_boarded (sürücü "müşteri araca bindi mi?" tuzak sorusuna evet dedi)
 *    └─ in_progress (sürücü "Yolculuğu Başlat" tıkladı, started_at set)
 *    └─ visual_verified (müşteri sürücü+araç fotoğraflarını eşleştirdi)
 *    └─ completed | cancelled | no_show | suspended_by_incident
 *
 * Bu migration:
 *  - ride_requests.status ENUM'una yeni durumlar ekler (string'e dönüştürerek)
 *  - dispatcher ve görsel doğrulama için yeni timestamp + payload kolonları ekler
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            // ─── Dispatcher / pool ───
            // Pool genişletme zamanı (T+30): bu vakit gelince cron seferi havuza yayar
            $table->timestamp('pool_expand_at')->nullable()->after('offer_expires_at')
                ->index()
                ->comment('Bu zamana kadar sürücü kabul etmezse havuza yayılır');

            // Havuza yayıldığında: kim aday oldu, kim reddetti
            $table->json('pool_candidate_driver_ids')->nullable()->after('pool_expand_at')
                ->comment('Pool expansion sırasında çağrılan tüm sürücü id listesi');
            $table->json('pool_rejected_driver_ids')->nullable()->after('pool_candidate_driver_ids')
                ->comment('Aday olup reddedenler');
            $table->timestamp('pool_expanded_at')->nullable()->after('pool_rejected_driver_ids');

            // ─── Müşteri yeniden onay (fallback sürücü için) ───
            // Pool'dan ilk kabul eden sürücü atandığında müşteriden onay beklenir
            $table->timestamp('reconfirm_required_at')->nullable()->after('pool_expanded_at')
                ->comment('Fallback sürücü atandığı an');
            $table->timestamp('customer_reconfirmed_at')->nullable()->after('reconfirm_required_at')
                ->comment('Müşteri yeni sürücüyü onayladığı an');
            $table->timestamp('customer_reconfirm_declined_at')->nullable()->after('customer_reconfirmed_at');

            // ─── Sürücü "müşteri araca bindi mi?" tuzak soru ───
            $table->timestamp('boarding_question_at')->nullable()->after('customer_confirmed_at')
                ->comment('Sürücüye "müşteri bindi mi?" sorusu gönderildi');
            $table->timestamp('boarding_confirmed_at')->nullable()->after('boarding_question_at')
                ->comment('Sürücü EVET dedi (tuzak: bu kabul, ride başlatma değil)');

            // ─── Yolculuk fiilen başladı ───
            $table->timestamp('started_at')->nullable()->after('boarding_confirmed_at')
                ->index()
                ->comment('Sürücü "Yolculuğu Başlat" butonuna bastığı an');

            // ─── Müşterinin görsel doğrulaması (ride başlangıcı) ───
            $table->timestamp('visual_verify_prompted_at')->nullable()->after('started_at');
            $table->timestamp('visual_verified_at')->nullable()->after('visual_verify_prompted_at')
                ->comment('Müşteri sürücü+araç fotoğraflarının doğru olduğunu onayladı');
            $table->timestamp('visual_verify_failed_at')->nullable()->after('visual_verified_at')
                ->comment('Müşteri "bu sürücü/araç değil" dedi → security incident tetiklenir');

            // ─── Yolculuk bitişi ───
            $table->timestamp('completed_at')->nullable()->after('visual_verify_failed_at')
                ->comment('Sürücü "Yolculuğu Tamamla" tıkladığı an');

            // İndeksleri yeni akış için
            $table->index(['status', 'pool_expand_at']);
            $table->index(['status', 'reconfirm_required_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ride_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'pool_expand_at']);
            $table->dropIndex(['status', 'reconfirm_required_at']);
            $table->dropColumn([
                'pool_expand_at',
                'pool_candidate_driver_ids',
                'pool_rejected_driver_ids',
                'pool_expanded_at',
                'reconfirm_required_at',
                'customer_reconfirmed_at',
                'customer_reconfirm_declined_at',
                'boarding_question_at',
                'boarding_confirmed_at',
                'started_at',
                'visual_verify_prompted_at',
                'visual_verified_at',
                'visual_verify_failed_at',
                'completed_at',
            ]);
        });
    }
};
