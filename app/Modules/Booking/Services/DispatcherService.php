<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RidePriceOffer;
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

    /** Havuz yarıçapı (km) — sürücünün kendi çapı yoksa (null) kullanılan varsayılan */
    public const POOL_MAX_KM = 5.0;

    /** Sürücünün ayarlayabileceği en geniş görünürlük çapı (km) — sistem tavanı */
    public const SERVICE_RADIUS_MAX_KM = 20.0;

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
     * NOT: favori dalgasındaki (is_favorite_wave=true) talepler HARİÇ — onları
     * tickFavoriteWaves() yakındaki havuza düşürür (erken exhaust olmasınlar).
     */
    public function tickStalePoolOffers(): int
    {
        $count = RideRequest::query()
            ->where('status', 'pool_expanded')
            ->where('is_favorite_wave', false)
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<=', now())
            ->update([
                'status' => 'exhausted',
                'updated_at' => now(),
            ]);
        return (int) $count;
    }

    /**
     * Favori dalgası süresi dolan (kimse kabul etmemiş) talepleri YAKINDAKİ havuza
     * düşürür. Favori sürücüler cevap vermediyse yolcu yakınındaki diğer üye
     * sürücülere teklif gider. Cron her dakika çağırır.
     */
    public function tickFavoriteWaves(): int
    {
        $waves = RideRequest::query()
            ->where('status', 'pool_expanded')
            ->where('is_favorite_wave', true)
            ->whereNull('accepted_driver_id')
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<=', now())
            ->limit(50)
            ->get();

        $processed = 0;
        foreach ($waves as $req) {
            if ($this->fallbackToNearbyPool($req)) {
                $processed++;
            }
        }
        return $processed;
    }

    /**
     * Favori dalgasını yakındaki havuza düşürür (favoriler hariç). Havuz boşsa
     * talep tükenir. Atomik: hala favori dalgasında + kimse kabul etmemişse çalışır.
     */
    private function fallbackToNearbyPool(RideRequest $req): bool
    {
        $poolDriverIds = [];

        $result = DB::transaction(function () use ($req, &$poolDriverIds) {
            $req = $req->fresh();
            if ($req->status !== 'pool_expanded' || $req->is_favorite_wave !== true || $req->accepted_driver_id) {
                return false;
            }

            // Favori sürücüleri + reddedenleri hariç tut
            $excludeIds = array_values(array_unique(array_map('intval', array_merge(
                $req->pool_candidate_driver_ids ?? [],
                $req->pool_rejected_driver_ids ?? [],
            ))));

            $candidates = $this->findNearestDispatchableDrivers(
                lat: (float) $req->pickup_lat,
                lng: (float) $req->pickup_lng,
                maxKm: self::POOL_MAX_KM,
                limit: self::POOL_MAX_SIZE,
                excludeIds: $excludeIds,
                vehicleClassId: $req->vehicle_class_id,
            );

            if (empty($candidates)) {
                RideRequest::where('id', $req->id)
                    ->where('status', 'pool_expanded')
                    ->where('is_favorite_wave', true)
                    ->update(['status' => 'exhausted', 'offer_expires_at' => null, 'updated_at' => now()]);
                RideMessage::create([
                    'ride_request_id' => $req->id,
                    'sender'          => 'system',
                    'body'            => 'Favori sürücülerin şu an uygun değil ve yakında başka üye sürücü bulunamadı.',
                ]);
                return true;
            }

            // Atomik: favori dalgasından yakın havuza geç
            $claimed = RideRequest::where('id', $req->id)
                ->where('status', 'pool_expanded')
                ->where('is_favorite_wave', true)
                ->whereNull('accepted_driver_id')
                ->update([
                    'is_favorite_wave'          => false,
                    'pool_candidate_driver_ids' => $candidates,
                    'pool_rejected_driver_ids'  => [],
                    'offer_expires_at'          => now()->addSeconds(self::POOL_OFFER_TTL_SECONDS),
                    'negotiation_state'         => 'customer_offered',
                    'driver_counter_fare'       => null,
                    'updated_at'                => now(),
                ]);

            if ($claimed === 0) {
                return false;
            }

            $poolDriverIds = $candidates;

            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Favori sürücülerinden dönüş olmadı — yakın bölgedeki ' . count($candidates) . ' üye sürücüye talep iletildi.',
            ]);

            return true;
        });

        if ($result && ! empty($poolDriverIds)) {
            $this->notifyPoolDrivers($poolDriverIds, $req->fresh());
        }

        return $result;
    }

    /**
     * Controller için: en yakın dispatchable sürücü id'leri (yakın havuz seed'i).
     * Auto modda favori yoksa doğrudan yakın havuza teklif göndermek için kullanılır.
     *
     * @return int[]
     */
    public function nearestDispatchableDriverIds(
        float $lat,
        float $lng,
        ?int $vehicleClassId = null,
        array $excludeIds = [],
    ): array {
        return $this->findNearestDispatchableDrivers(
            lat: $lat,
            lng: $lng,
            maxKm: self::POOL_MAX_KM,
            limit: self::POOL_MAX_SIZE,
            excludeIds: $excludeIds,
            vehicleClassId: $vehicleClassId,
        );
    }

    /**
     * Bir ride request'i havuza yayar.
     * Atomik: hala pending mi diye kontrol eder.
     */
    public function expandToPool(RideRequest $req): bool
    {
        $poolDriverIds = [];

        $result = DB::transaction(function () use ($req, &$poolDriverIds) {
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

            // Havuza yayılırken pazarlığı yolcunun MASADAKİ teklifine sıfırla:
            // 1:1'de seçilen sürücünün karşı teklifi havuza taşınmaz, herkes yolcunun
            // güncel fiyatına (customer_offer_fare) cevap verir.
            RideRequest::where('id', $req->id)
                ->update([
                    'pool_candidate_driver_ids' => $candidates,
                    'offer_expires_at'          => now()->addSeconds(self::POOL_OFFER_TTL_SECONDS),
                    'negotiation_state'         => 'customer_offered',
                    'driver_counter_fare'       => null,
                    'updated_at'                => now(),
                ]);

            $poolDriverIds = $candidates;

            // Sistem mesajı
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Sürücü cevap vermedi — yakın bölgedeki ' . count($candidates) . ' üye sürücüye talep iletildi.',
            ]);

            return true;
        });

        if ($result && ! empty($poolDriverIds)) {
            $this->notifyPoolDrivers($poolDriverIds, $req->fresh());
        }

        return $result;
    }

    /**
     * Havuza yeni eklenen sürücülere "yeni teklif" push'u (best-effort).
     * RideRequestService::notifyNewOffer ile aynı desen — bildirim hatası
     * dispatch akışını ASLA bozmaz.
     *
     * @param  int[]  $driverIds
     */
    private function notifyPoolDrivers(array $driverIds, RideRequest $req): void
    {
        try {
            $driverIds = array_values(array_filter(array_map('intval', $driverIds)));
            if (! empty($driverIds)) {
                app(\App\Modules\Notification\Services\NotificationService::class)
                    ->rideOfferToDrivers($driverIds, $req);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[DispatcherService] pool offer push', ['err' => $e->getMessage()]);
        }
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

        // Atomik claim — pool sürücüsü yolcunun MASADAKİ fiyatını kabul etti
        $claimed = RideRequest::where('id', $req->id)
            ->where('status', 'pool_expanded')
            ->whereNull('accepted_driver_id')
            ->update([
                'status'                => 'awaiting_customer_reconfirm',
                'reconfirm_required_at' => now(),
                'accepted_driver_id'    => $driver->id,
                'negotiation_state'     => 'customer_offered',
                'offer_expires_at'      => now()->addSeconds(self::RECONFIRM_TTL_SECONDS),
                'updated_at'            => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $this->logPoolOffer($req, $driver->id, 'accept', $req->customer_offer_fare !== null ? (float) $req->customer_offer_fare : null);

        // Sistem mesajı
        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Sizin için yakındaki bir üye sürücü bulundu. Onayınız bekleniyor.',
        ]);

        return true;
    }

    /**
     * Havuzdaki bir sürücü, yolcunun fiyatına karşı teklif verir.
     * İlk karşı teklif veren "kilidi" alır → müşteri onayına düşer (fiyatı da onaylar).
     */
    public function counterByPoolDriver(RideRequest $req, Driver $driver, float $amount): bool
    {
        $req = $req->fresh();
        if ($req->status !== 'pool_expanded') return false;

        $candidates = $req->pool_candidate_driver_ids ?? [];
        if (! in_array($driver->id, $candidates, true)) return false;
        if (! $driver->isDispatchable()) return false;

        $amount = round(max(0.0, $amount), 2);

        // Atomik claim — ilk cevaplayan alır
        $claimed = RideRequest::where('id', $req->id)
            ->where('status', 'pool_expanded')
            ->whereNull('accepted_driver_id')
            ->update([
                'status'                => 'awaiting_customer_reconfirm',
                'reconfirm_required_at' => now(),
                'accepted_driver_id'    => $driver->id,
                'driver_counter_fare'   => $amount,
                'negotiation_state'     => 'driver_countered',
                'offer_expires_at'      => now()->addSeconds(self::RECONFIRM_TTL_SECONDS),
                'updated_at'            => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $this->logPoolOffer($req, $driver->id, 'counter', $amount);

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Yakındaki bir üye sürücü ' . number_format($amount, 2, ',', '.') . ' ₺ teklif etti. Onayınız bekleniyor.',
        ]);

        return true;
    }

    private function logPoolOffer(RideRequest $req, int $driverId, string $type, ?float $amount): void
    {
        RidePriceOffer::create([
            'ride_request_id' => $req->id,
            'driver_id'       => $driverId,
            'actor'           => 'driver',
            'type'            => $type,
            'amount'          => $amount,
            'round'           => (int) $req->negotiation_round,
        ]);
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
            ->whereNotNull('current_lng')
            // Bayat konumlu sürücüye iş atama (panel kapalı, telefon uyku vs.)
            ->where('last_location_updated_at', '>=', now()->subMinutes(3));

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Vehicle class filtresi (varsa)
        if ($vehicleClassId) {
            $query->whereHas('currentVehicle', function ($q) use ($vehicleClassId) {
                $q->where('vehicle_class_id', $vehicleClassId);
            });
        }

        $drivers = $query->limit(200)->get(['id', 'current_lat', 'current_lng', 'service_radius_km']);

        $scored = $drivers->map(function ($d) use ($lat, $lng, $maxKm) {
            $km = $this->haversineKm($lat, $lng, (float) $d->current_lat, (float) $d->current_lng);
            // Sürücünün kendi çapı; yoksa çağıranın verdiği varsayılan. Sistem tavanı ile sınırlı.
            $radius = min(
                (float) ($d->service_radius_km ?? $maxKm),
                self::SERVICE_RADIUS_MAX_KM,
            );
            return ['id' => (int) $d->id, 'km' => $km, 'radius' => $radius];
        })
            ->filter(fn ($x) => $x['km'] <= $x['radius'])
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
