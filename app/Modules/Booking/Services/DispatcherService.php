<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Support\Facades\DB;

/**
 * Dispatcher — havuz genişletme + müşteri yeniden onay akışı.
 *
 * Akış:
 *   1. Müşteri tek sürücü seçer → RideRequestService::create() (status=pending)
 *      pool_expand_at = now+30sn
 *   2. Seçilen sürücü 30 sn cevap vermez → cron tickPendingExpansions() çağrısı
 *      expandToPool() çalışır: status=pool_expanded, en yakın N sürücü teklif
 *   3. Havuzdaki ilk kabul eden sürücü acceptByPoolDriver() ile alır
 *      → status=awaiting_customer_reconfirm, müşteri onay versin
 *   4. Müşteri customerReconfirm(true) → RideRequestService::accept() çağrılır → Ride yaratılır
 *      Müşteri customerReconfirm(false) → status=cancelled
 */
class DispatcherService
{
    /** Havuza katılan sürücülere verilen kabul süresi (sn) */
    public const POOL_OFFER_TTL_SECONDS = 45;

    /** Havuza alınacak maksimum sürücü sayısı */
    public const POOL_MAX_SIZE = 8;

    /** Havuz yarıçapı (km) */
    public const POOL_MAX_KM = 5.0;

    /** Müşteriye reconfirm için verilen süre (sn) */
    public const RECONFIRM_TTL_SECONDS = 60;

    public function __construct(
        private RideRequestService $rideRequestService,
    ) {}

    /**
     * Cron her dakika çağırır. Süresi gelmiş pending talepleri havuza yayar.
     *
     * @return int İşlenen talep sayısı
     */
    public function tickPendingExpansions(): int
    {
        $candidates = RideRequest::query()
            ->where('status', 'pending')
            ->whereNotNull('pool_expand_at')
            ->where('pool_expand_at', '<=', now())
            ->limit(50)
            ->get();

        $processed = 0;
        foreach ($candidates as $req) {
            if ($this->expandToPool($req)) {
                $processed++;
            }
        }
        return $processed;
    }

    /**
     * Süresi gelmiş awaiting_customer_reconfirm talepleri otomatik iptal eder.
     * Cron her dakika çağırır.
     */
    public function tickStaleReconfirms(): int
    {
        $count = RideRequest::query()
            ->where('status', 'awaiting_customer_reconfirm')
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<=', now())
            ->update([
                'status' => 'cancelled',
                'customer_reconfirm_declined_at' => now(),
                'updated_at' => now(),
            ]);
        return (int) $count;
    }

    /**
     * Süresi gelmiş pool_expanded talepleri tüketildi (exhausted) yapar.
     */
    public function tickStalePoolOffers(): int
    {
        $count = RideRequest::query()
            ->where('status', 'pool_expanded')
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<=', now())
            ->update([
                'status' => 'exhausted',
                'updated_at' => now(),
            ]);
        return (int) $count;
    }

    /**
     * Bir ride request'i havuza yayar.
     * Atomik: hala pending mi diye kontrol eder.
     */
    public function expandToPool(RideRequest $req): bool
    {
        return DB::transaction(function () use ($req) {
            // Atomik claim
            $claimed = RideRequest::where('id', $req->id)
                ->where('status', 'pending')
                ->update([
                    'status'           => 'pool_expanded',
                    'pool_expanded_at' => now(),
                    'updated_at'       => now(),
                ]);

            if ($claimed === 0) {
                return false;
            }

            $req = $req->fresh();

            // Mevcut offered_driver_id'yi havuzdan çıkar (zaten kabul etmedi)
            $excludeIds = array_filter([
                (int) $req->offered_driver_id,
                ...($req->candidate_driver_ids ?? []),
            ]);

            $candidates = $this->findNearestDispatchableDrivers(
                lat: (float) $req->pickup_lat,
                lng: (float) $req->pickup_lng,
                maxKm: self::POOL_MAX_KM,
                limit: self::POOL_MAX_SIZE,
                excludeIds: $excludeIds,
                vehicleClassId: $req->vehicle_class_id,
            );

            if (empty($candidates)) {
                // Havuz boş → talep tükendi
                RideRequest::where('id', $req->id)
                    ->update([
                        'status'           => 'exhausted',
                        'offer_expires_at' => null,
                        'updated_at'       => now(),
                    ]);
                return true;
            }

            RideRequest::where('id', $req->id)
                ->update([
                    'pool_candidate_driver_ids' => $candidates,
                    'offer_expires_at'          => now()->addSeconds(self::POOL_OFFER_TTL_SECONDS),
                    'updated_at'                => now(),
                ]);

            // Sistem mesajı
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Sürücü cevap vermedi — yakın bölgedeki ' . count($candidates) . ' üye sürücüye talep iletildi.',
            ]);

            return true;
        });
    }

    /**
     * Havuzdaki bir sürücü kabul ettiğinde çağrılır.
     * İlk kabul eden alır — atomik.
     */
    public function acceptByPoolDriver(RideRequest $req, Driver $driver): bool
    {
        $req = $req->fresh();
        if ($req->status !== 'pool_expanded') {
            return false;
        }

        $candidates = $req->pool_candidate_driver_ids ?? [];
        if (! in_array($driver->id, $candidates, true)) {
            return false;
        }

        if (! $driver->isDispatchable()) {
            return false;
        }

        // Atomik claim
        $claimed = RideRequest::where('id', $req->id)
            ->where('status', 'pool_expanded')
            ->whereNull('accepted_driver_id')
            ->update([
                'status'                => 'awaiting_customer_reconfirm',
                'reconfirm_required_at' => now(),
                'accepted_driver_id'    => $driver->id,
                'offer_expires_at'      => now()->addSeconds(self::RECONFIRM_TTL_SECONDS),
                'updated_at'            => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        // Sistem mesajı
        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Sizin için yakındaki bir üye sürücü bulundu. Onayınız bekleniyor.',
        ]);

        return true;
    }

    /**
     * Havuzdaki bir sürücü reddederse çağrılır — rejected listesine ekler.
     */
    public function rejectByPoolDriver(RideRequest $req, Driver $driver): bool
    {
        $req = $req->fresh();
        if ($req->status !== 'pool_expanded') return false;

        $rejected = $req->pool_rejected_driver_ids ?? [];
        if (in_array($driver->id, $rejected, true)) return true; // zaten reddetti

        $rejected[] = $driver->id;
        $req->update(['pool_rejected_driver_ids' => $rejected]);

        // Tüm havuz reddetmişse exhausted yap
        $candidates = $req->pool_candidate_driver_ids ?? [];
        if (count($rejected) >= count($candidates)) {
            RideRequest::where('id', $req->id)
                ->where('status', 'pool_expanded')
                ->update([
                    'status'           => 'exhausted',
                    'offer_expires_at' => null,
                    'updated_at'       => now(),
                ]);
        }

        return true;
    }

    /**
     * Müşteri yeniden onayını gönderir.
     *  - $accept=true  → status=accepted, ride yaratılır
     *  - $accept=false → status=cancelled
     */
    public function customerReconfirm(RideRequest $req, bool $accept): RideRequest
    {
        $req = $req->fresh();
        if ($req->status !== 'awaiting_customer_reconfirm') {
            return $req;
        }

        if (! $accept) {
            $req->update([
                'status'                          => 'cancelled',
                'customer_reconfirm_declined_at'  => now(),
            ]);
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Müşteri eşleştirilen üye sürücüyü onaylamadı, talep iptal edildi.',
            ]);
            return $req->fresh();
        }

        // Onayladı → mevcut accept() metodunu çağır
        $driver = Driver::find($req->accepted_driver_id);
        if (! $driver) {
            $req->update(['status' => 'exhausted']);
            return $req->fresh();
        }

        $req->update(['customer_reconfirmed_at' => now()]);

        return $this->rideRequestService->accept($req->fresh(), $driver);
    }

    /**
     * Haversine ile en yakın N dispatchable sürücüyü bul.
     * (Üretimde PostGIS / MySQL spatial query ile değiştirilmeli — şimdilik full scan.)
     *
     * @return int[] driver IDs
     */
    private function findNearestDispatchableDrivers(
        float $lat,
        float $lng,
        float $maxKm,
        int $limit,
        array $excludeIds = [],
        ?int $vehicleClassId = null,
    ): array {
        $query = Driver::query()
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->where('is_suspended', false)
            ->whereNotNull('package_active_until')
            ->where('package_active_until', '>', now())
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng');

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Vehicle class filtresi (varsa)
        if ($vehicleClassId) {
            $query->whereHas('currentVehicle', function ($q) use ($vehicleClassId) {
                $q->where('vehicle_class_id', $vehicleClassId);
            });
        }

        $drivers = $query->limit(200)->get(['id', 'current_lat', 'current_lng']);

        $scored = $drivers->map(function ($d) use ($lat, $lng) {
            $km = $this->haversineKm($lat, $lng, (float) $d->current_lat, (float) $d->current_lng);
            return ['id' => (int) $d->id, 'km' => $km];
        })
            ->filter(fn ($x) => $x['km'] <= $maxKm)
            ->sortBy('km')
            ->take($limit)
            ->pluck('id')
            ->values()
            ->all();

        return array_map('intval', $scored);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earthKm * asin(min(1.0, sqrt($a)));
    }
}
