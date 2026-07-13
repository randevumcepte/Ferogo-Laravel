<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RidePriceOffer;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shared\Models\City;
use Illuminate\Support\Facades\DB;

class RideRequestService
{
    /** Bir teklifin geçerlilik süresi (sn) — sürücü bu sürede cevap vermezse sıradakine geçer. */
    public const OFFER_TTL_SECONDS = 60;

    /** Seçilen sürücü kabul etmezse havuza yayılma süresi (sn) — Faz 3 dispatcher. */
    public const POOL_EXPAND_AFTER_SECONDS = 30;

    /** Favori dalgası / doğrudan havuz teklifinin geçerlilik süresi (sn). */
    public const FAVORITE_WAVE_TTL_SECONDS = 45;

    /** Fiyat pazarlığında izin verilen maksimum karşı-teklif turu (sonra sadece kabul/ret). */
    public const MAX_NEGOTIATION_ROUNDS = 4;

    /** Teklifler sistem önerisinden en fazla bu oranda sapabilir (±%). */
    public const PRICE_BAND = 0.40;

    /**
     * Bir teklifin sistem önerisine (çapa) göre izinli alt/üst sınırını döner.
     *
     * @return array{0: float, 1: float} [min, max]
     */
    public function priceBounds(float $suggested): array
    {
        $suggested = max(0.0, $suggested);
        return [
            round($suggested * (1 - self::PRICE_BAND), 2),
            round($suggested * (1 + self::PRICE_BAND), 2),
        ];
    }

    /** Teklif fiyatı banda göre kırpılır (kötüye kullanım + "damping" görünümü önlenir). */
    public function clampToBand(float $amount, ?float $suggested): float
    {
        if (! $suggested || $suggested <= 0) {
            return round(max(0.0, $amount), 2);
        }
        [$min, $max] = $this->priceBounds($suggested);
        return round(min($max, max($min, $amount)), 2);
    }

    public function __construct(
        private ReservationService $reservationService,
        private CustomerTrustService $trustService,
    ) {}

    /**
     * Yeni bir ride_request yarat: ilk adaya hemen teklif et.
     *
     * @param array{
     *   customer_name:string,
     *   customer_phone:string,
     *   vehicle_class_id:int,
     *   pickup_address:string,
     *   pickup_lat:float,
     *   pickup_lng:float,
     *   dropoff_address:string,
     *   dropoff_lat?:?float,
     *   dropoff_lng?:?float,
     *   distance_km:float,
     *   duration_minutes:int,
     *   estimated_fare?:?float,
     *   candidate_driver_ids:array<int>, // [seçilen, fallback1, fallback2, ...]
     * } $data
     */
    public function create(array $data): RideRequest
    {
        $created = DB::transaction(function () use ($data) {
            // ─── Fiyat pazarlığı başlangıcı ───
            // suggested_fare = sistem önerisi (çapa). customer_offer_fare = yolcunun
            // +/- ile belirlediği ilk teklif (verilmezse öneriye eşit). Banda kırpılır.
            $suggested = isset($data['suggested_fare'])
                ? (float) $data['suggested_fare']
                : (isset($data['estimated_fare']) ? (float) $data['estimated_fare'] : null);

            $customerOffer = isset($data['customer_offer_fare'])
                ? $this->clampToBand((float) $data['customer_offer_fare'], $suggested)
                : $suggested;

            // Ortak alanlar (her iki dispatch şekli için).
            $base = [
                'customer_name'       => $data['customer_name'],
                'customer_phone'      => $data['customer_phone'],
                'phone_verified_at'   => $data['phone_verified_at'] ?? null,
                'verification_token'  => $data['verification_token'] ?? null,
                'client_ip'           => $data['client_ip'] ?? null,
                'client_fingerprint'  => $data['client_fingerprint'] ?? null,
                'user_agent'          => $data['user_agent'] ?? null,
                'vehicle_class_id'    => $data['vehicle_class_id'],
                'pickup_address'      => $data['pickup_address'],
                'pickup_lat'          => $data['pickup_lat'],
                'pickup_lng'          => $data['pickup_lng'],
                'dropoff_address'     => $data['dropoff_address'],
                'dropoff_lat'         => $data['dropoff_lat'] ?? null,
                'dropoff_lng'         => $data['dropoff_lng'] ?? null,
                'distance_km'         => $data['distance_km'],
                'duration_minutes'    => $data['duration_minutes'],
                'estimated_fare'      => $data['estimated_fare'] ?? null,
                'suggested_fare'      => $suggested,
                'customer_offer_fare' => $customerOffer,
                'negotiation_state'   => 'customer_offered',
                'negotiation_round'   => 0,
            ];

            // ─── Şekil A: doğrudan HAVUZ teklifi (favori dalgası veya yakın havuz) ───
            // pool_driver_ids verildiyse tek bir sürücüye değil, birden çok sürücüye
            // AYNI ANDA teklif gider (Martı/inDrive havuz mantığı). is_favorite_wave=true
            // ise bunlar yolcunun online favori sürücüleridir; dönüş olmazsa cron
            // (tickFavoriteWaves) yakındaki havuza düşürür.
            $poolIds = array_values(array_filter(
                array_map('intval', $data['pool_driver_ids'] ?? []),
                fn ($id) => $id > 0
            ));

            if (! empty($poolIds)) {
                $req = RideRequest::create($base + [
                    'status'                    => 'pool_expanded',
                    'is_favorite_wave'          => (bool) ($data['is_favorite_wave'] ?? false),
                    'pool_candidate_driver_ids' => $poolIds,
                    'pool_expanded_at'          => now(),
                    'offer_expires_at'          => now()->addSeconds(self::FAVORITE_WAVE_TTL_SECONDS),
                    'current_candidate_index'   => 0,
                ]);

                if ($customerOffer !== null) {
                    $this->logOffer($req, 'customer', 'offer', $customerOffer, 0);
                }

                return $req->fresh();
            }

            // ─── Şekil B: 1:1 aday akışı (yolcu manuel sürücü seçti) ───
            $candidates = array_values(array_filter(
                array_map('intval', $data['candidate_driver_ids'] ?? []),
                fn ($id) => $id > 0
            ));
            if (empty($candidates)) {
                throw new \InvalidArgumentException('En az 1 aday sürücü gerekli.');
            }

            $req = RideRequest::create($base + [
                'status'                  => 'pending',
                'candidate_driver_ids'    => $candidates,
                'current_candidate_index' => 0,
                'offered_driver_id'       => $candidates[0],
                'offer_expires_at'        => now()->addSeconds(self::OFFER_TTL_SECONDS),
                // Faz 3: seçilen sürücü 30 sn içinde cevap vermezse havuza yayılır
                'pool_expand_at'          => now()->addSeconds(self::POOL_EXPAND_AFTER_SECONDS),
            ]);

            if ($customerOffer !== null) {
                $this->logOffer($req, 'customer', 'offer', $customerOffer, 0);
            }

            return $req->fresh();
        });

        // Yeni talep oluştu → hedef sürücü(ler)e push bildirimi (best-effort).
        $this->notifyNewOffer($created);

        return $created;
    }

    /** Yeni oluşturulan talep için hedef sürücülere "yeni teklif" bildirimi. */
    private function notifyNewOffer(RideRequest $req): void
    {
        try {
            $driverIds = $req->status === 'pool_expanded'
                ? ($req->pool_candidate_driver_ids ?? [])
                : array_values(array_filter([$req->offered_driver_id]));

            if (! empty($driverIds)) {
                app(\App\Modules\Notification\Services\NotificationService::class)
                    ->rideOfferToDrivers($driverIds, $req);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RideRequestService] offer push', ['err' => $e->getMessage()]);
        }
    }

    /** Pazarlık adımını denetim kaydına yazar (sessiz, best-effort). */
    private function logOffer(RideRequest $req, string $actor, string $type, ?float $amount, int $round, ?int $driverId = null): void
    {
        RidePriceOffer::create([
            'ride_request_id' => $req->id,
            'driver_id'       => $driverId,
            'actor'           => $actor,
            'type'            => $type,
            'amount'          => $amount,
            'round'           => $round,
        ]);
    }

    /**
     * Mevcut teklif süresi dolduysa otomatik olarak bir sonraki adaya geç.
     * Polling endpoint'lerinin başında çağrılır — self-heal.
     */
    public function tickExpiry(RideRequest $req): RideRequest
    {
        if ($req->status === 'pending' && $req->offerExpired()) {
            $req->increment('rejection_count');
            $this->advanceOrExhaust($req->fresh());
        }
        return $req->fresh();
    }

    /**
     * Sürücü teklifi reddederse — sıradakine geç.
     * Yarış koşulu (driver A reject + driver B accept aynı anda) bu sürümde
     * yumuşak: status pending kontrolü atomik update ile.
     */
    public function reject(RideRequest $req, Driver $rejectingDriver): RideRequest
    {
        if ($req->status !== 'pending') return $req->fresh();
        if ((int) $req->offered_driver_id !== (int) $rejectingDriver->id) {
            // Bu sürücüye artık teklif yok (örn. süresi dolmuş)
            return $req->fresh();
        }

        $req->increment('rejection_count');
        return $this->advanceOrExhaust($req->fresh());
    }

    /**
     * Sürücü teklifi kabul ederse — Ride yarat, request'i kapat.
     * Atomik: status='pending' + offered_driver_id eşleşmesi şartıyla.
     */
    public function accept(RideRequest $req, Driver $acceptingDriver, ?float $agreedFare = null): RideRequest
    {
        $result = DB::transaction(function () use ($req, $acceptingDriver, $agreedFare) {
            // Atomik claim — direkt seçilen sürücü teklifi VE
            // müşterinin reconfirm onayı sonrası (havuzdaki sürücüyü accept et) iki durumu kapsar
            $current = $req->fresh();
            $statusBefore = $current->status;

            // Anlaşılan ücret: açıkça verildiyse onu, yoksa pazarlığın masadaki son değerini kullan.
            $agreedFare ??= $current->currentPrice();

            if ($statusBefore === 'awaiting_customer_reconfirm') {
                // Reconfirm'de zaten accepted_driver_id set ve müşteri onayladı → direkt accepted'a geç
                $updated = RideRequest::where('id', $req->id)
                    ->where('status', 'awaiting_customer_reconfirm')
                    ->where('accepted_driver_id', $acceptingDriver->id)
                    ->update([
                        'status'              => 'accepted',
                        'accepted_at'         => now(),
                        'offer_expires_at'    => null,
                        'agreed_fare'         => $agreedFare,
                        'negotiation_state'   => 'agreed',
                        'updated_at'          => now(),
                    ]);
            } else {
                // Normal akış: pending → accepted (atomik)
                $updated = RideRequest::where('id', $req->id)
                    ->where('status', 'pending')
                    ->where('offered_driver_id', $acceptingDriver->id)
                    ->update([
                        'status'              => 'accepted',
                        'accepted_at'         => now(),
                        'accepted_driver_id'  => $acceptingDriver->id,
                        'offer_expires_at'    => null,
                        'pool_expand_at'      => null,
                        'agreed_fare'         => $agreedFare,
                        'negotiation_state'   => 'agreed',
                        'updated_at'          => now(),
                    ]);
            }

            if ($updated === 0) {
                throw new \RuntimeException('Bu talep artık geçerli değil (başkası aldı ya da süresi doldu).');
            }

            $this->logOffer($req, 'driver', 'accept', $agreedFare, (int) $current->negotiation_round, $acceptingDriver->id);

            $req = $req->fresh();

            // Ride kaydını üret
            $city = City::where('is_active', true)
                ->where(function ($q) {
                    $q->where('slug', 'izmir')->orWhere('name', 'like', '%zmir%');
                })
                ->orderBy('sort_order')
                ->first()
                ?? City::where('is_active', true)->orderBy('sort_order')->firstOrFail();

            $ride = $this->reservationService->create([
                'city_id'           => $city->id,
                'vehicle_class_id'  => $req->vehicle_class_id,
                'customer_name'     => $req->customer_name,
                'customer_phone'    => $req->customer_phone,
                'pickup_address'    => $req->pickup_address,
                'pickup_lat'        => (float) $req->pickup_lat,
                'pickup_lng'        => (float) $req->pickup_lng,
                'pickup_notes'      => 'Radar · Talep #' . substr($req->public_id, 0, 8),
                'dropoff_address'   => $req->dropoff_address,
                'dropoff_lat'       => $req->dropoff_lat ? (float) $req->dropoff_lat : null,
                'dropoff_lng'       => $req->dropoff_lng ? (float) $req->dropoff_lng : null,
                'distance_km'       => (float) $req->distance_km,
                'duration_minutes'  => (int) $req->duration_minutes,
                'passenger_count'   => 1,
                'luggage_count'     => 0,
                'scheduled_at'      => now()->addMinutes(2)->toIso8601String(),
                'source'            => 'web',
            ]);

            // Ride'a sürücü ata + status=assigned + anlaşılan ücret
            $rideUpdate = [
                'driver_id'   => $acceptingDriver->id,
                'status'      => 'driver_arriving',
                'assigned_at' => now(),
                'confirmed_at' => now(),
            ];
            if ($agreedFare !== null) {
                $rideUpdate['total_fare'] = round($agreedFare, 2);
            }
            $ride->update($rideUpdate);

            // Driver busy
            $acceptingDriver->update(['availability_status' => 'busy']);

            // Eşleşme kodu — buluşmada sürücü girer, yolculuk başlar (yalnız yolcuya gösterilir)
            $req->update([
                'ride_id'    => $ride->id,
                'match_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            ]);

            // Sistem mesajı (chat'in başına düşsün) — anlaşılan ücreti de yaz
            $etaBody = 'Üye sürücü yola çıktı. Tahmini varış: ' . max(1, (int) round((float) $req->distance_km * 2.4)) . ' dk.';
            if ($agreedFare !== null) {
                $etaBody .= ' Anlaşılan ücret: ' . number_format($agreedFare, 2, ',', '.') . ' ₺.';
            }
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => $etaBody,
            ]);

            return $req->fresh();
        });

        // Sürücü kabul etti → müşteriye push (best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->rideAcceptedToCustomer($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RideRequestService] accept push', ['err' => $e->getMessage()]);
        }

        return $result;
    }

    /** Müşteri talebi iptal eder (henüz kimse kabul etmemişken). */
    public function cancelByCustomer(RideRequest $req): RideRequest
    {
        if ($req->status === 'pending') {
            $req->update([
                'status'           => 'cancelled',
                'offered_driver_id' => null,
                'offer_expires_at' => null,
            ]);
            // Trust skoruna işle — kimse kabul etmeden iptal: küçük penaltı
            $this->trustService->recordCustomerCancellation($req->customer_phone, late: false);
        } elseif ($req->status === 'accepted') {
            // Sürücü zaten geldi yoldaydı — büyük penaltı
            $req->update([
                'status' => 'cancelled',
            ]);
            $this->trustService->recordCustomerCancellation($req->customer_phone, late: true);

            // Sürücü buluşmaya gidiyor olabilir → iptal push'u (best-effort).
            try {
                app(\App\Modules\Notification\Services\NotificationService::class)
                    ->rideCancelledToDriver($req);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[RideRequestService] cancel push', ['err' => $e->getMessage()]);
            }
        }
        return $req->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    //  FİYAT PAZARLIĞI (1:1 — seçilen sürücüyle)
    // ─────────────────────────────────────────────────────────────

    /**
     * Sürücü, yolcunun teklifine karşı fiyat verir (top yolcuya geçer).
     *
     * @return array{ok:bool, message?:string, amount?:float, round?:int, rounds_left?:int, limit_reached?:bool}
     */
    public function driverCounter(RideRequest $req, Driver $driver, float $amount): array
    {
        $req = $req->fresh();

        if ($req->status !== 'pending' || (int) $req->offered_driver_id !== (int) $driver->id) {
            return ['ok' => false, 'message' => 'Bu teklif artık geçerli değil.'];
        }
        if ($req->negotiation_state !== 'customer_offered') {
            return ['ok' => false, 'message' => 'Şu an karşı teklif veremezsin (sıra sende değil).'];
        }
        if ((int) $req->negotiation_round >= self::MAX_NEGOTIATION_ROUNDS) {
            return ['ok' => false, 'limit_reached' => true, 'message' => 'Pazarlık turu doldu. Kabul et ya da reddet.'];
        }

        $amount = $this->clampToBand($amount, $req->suggested_fare !== null ? (float) $req->suggested_fare : null);
        $round  = (int) $req->negotiation_round + 1;

        $req->update([
            'driver_counter_fare' => $amount,
            'negotiation_state'   => 'driver_countered',
            'negotiation_round'   => $round,
            'offer_expires_at'    => now()->addSeconds(self::OFFER_TTL_SECONDS),
            // Aktif pazarlık: havuza yayılmayı yolcunun cevap penceresi kadar ertele
            'pool_expand_at'      => now()->addSeconds(self::OFFER_TTL_SECONDS),
        ]);
        $this->logOffer($req, 'driver', 'counter', $amount, $round, $driver->id);

        // Sürücü karşı teklif verdi → müşteriye push (best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->driverCounterToCustomer($req, $amount);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RideRequestService] driver counter push', ['err' => $e->getMessage()]);
        }

        return [
            'ok'          => true,
            'amount'      => $amount,
            'round'       => $round,
            'rounds_left' => max(0, self::MAX_NEGOTIATION_ROUNDS - $round),
        ];
    }

    /**
     * Yolcu, sürücünün karşı teklifine yeni bir teklifle cevap verir (top sürücüye geçer).
     *
     * @return array{ok:bool, message?:string, amount?:float, round?:int, rounds_left?:int, limit_reached?:bool}
     */
    public function customerCounter(RideRequest $req, float $amount): array
    {
        $req = $req->fresh();

        if ($req->status !== 'pending') {
            return ['ok' => false, 'message' => 'Bu talep artık aktif değil.'];
        }
        if ($req->negotiation_state !== 'driver_countered') {
            return ['ok' => false, 'message' => 'Şu an karşı teklif veremezsin (sıra sende değil).'];
        }
        if ((int) $req->negotiation_round >= self::MAX_NEGOTIATION_ROUNDS) {
            return ['ok' => false, 'limit_reached' => true, 'message' => 'Pazarlık turu doldu. Sürücünün fiyatını kabul et ya da vazgeç.'];
        }

        $amount = $this->clampToBand($amount, $req->suggested_fare !== null ? (float) $req->suggested_fare : null);
        $round  = (int) $req->negotiation_round + 1;

        $req->update([
            'customer_offer_fare' => $amount,
            'negotiation_state'   => 'customer_offered',
            'negotiation_round'   => $round,
            'offer_expires_at'    => now()->addSeconds(self::OFFER_TTL_SECONDS),
            'pool_expand_at'      => now()->addSeconds(self::OFFER_TTL_SECONDS),
        ]);
        $this->logOffer($req, 'customer', 'counter', $amount, $round);

        // Yolcu karşı teklif verdi → sürücüye push (best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->customerCounterToDriver($req, $amount);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RideRequestService] customer counter push', ['err' => $e->getMessage()]);
        }

        return [
            'ok'          => true,
            'amount'      => $amount,
            'round'       => $round,
            'rounds_left' => max(0, self::MAX_NEGOTIATION_ROUNDS - $round),
        ];
    }

    /**
     * Yolcu, sürücünün karşı teklifini kabul eder → anlaşma, yolculuk başlar.
     *
     * @return array{ok:bool, message?:string, request?:RideRequest}
     */
    public function customerAcceptCounter(RideRequest $req): array
    {
        $req = $req->fresh();

        if ($req->status !== 'pending' || $req->negotiation_state !== 'driver_countered') {
            return ['ok' => false, 'message' => 'Kabul edilecek bir karşı teklif yok.'];
        }

        $driver = Driver::find($req->offered_driver_id);
        if (! $driver) {
            return ['ok' => false, 'message' => 'Sürücü artık müsait değil.'];
        }

        $agreed = $req->driver_counter_fare !== null ? (float) $req->driver_counter_fare : $req->currentPrice();
        $this->logOffer($req, 'customer', 'accept', $agreed, (int) $req->negotiation_round, $driver->id);

        try {
            $req = $this->accept($req->fresh(), $driver, $agreed);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        // Yolcu sürücünün karşı teklifini kabul etti → sürücüye push (best-effort).
        // (accept() zaten müşteriye "sürücün yolda" atar; burada sürücü haberdar edilir.)
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->agreementToDriver($req);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RideRequestService] agreement push', ['err' => $e->getMessage()]);
        }

        return ['ok' => true, 'request' => $req];
    }

    private function advanceOrExhaust(RideRequest $req): RideRequest
    {
        $candidates = $req->candidate_driver_ids ?? [];
        $nextIndex = $req->current_candidate_index + 1;

        if ($nextIndex >= count($candidates)) {
            $req->update([
                'status'           => 'exhausted',
                'offered_driver_id' => null,
                'offer_expires_at' => null,
            ]);
            return $req->fresh();
        }

        // Yeni sürücüye geçerken pazarlığı yolcunun masadaki teklifine sıfırla:
        // önceki sürücünün karşı teklifi taşınmasın, tur sayacı yeniden başlasın.
        $req->update([
            'current_candidate_index' => $nextIndex,
            'offered_driver_id'       => $candidates[$nextIndex],
            'offer_expires_at'        => now()->addSeconds(self::OFFER_TTL_SECONDS),
            'negotiation_state'       => 'customer_offered',
            'driver_counter_fare'     => null,
            'negotiation_round'       => 0,
        ]);
        return $req->fresh();
    }
}
