<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\RideMessage;
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
        return DB::transaction(function () use ($data) {
            $candidates = array_values(array_filter(
                array_map('intval', $data['candidate_driver_ids']),
                fn ($id) => $id > 0
            ));
            if (empty($candidates)) {
                throw new \InvalidArgumentException('En az 1 aday sürücü gerekli.');
            }

            $req = RideRequest::create([
                'customer_name'           => $data['customer_name'],
                'customer_phone'          => $data['customer_phone'],
                'phone_verified_at'       => $data['phone_verified_at'] ?? null,
                'verification_token'      => $data['verification_token'] ?? null,
                'client_ip'               => $data['client_ip'] ?? null,
                'client_fingerprint'      => $data['client_fingerprint'] ?? null,
                'user_agent'              => $data['user_agent'] ?? null,
                'vehicle_class_id'        => $data['vehicle_class_id'],
                'pickup_address'          => $data['pickup_address'],
                'pickup_lat'              => $data['pickup_lat'],
                'pickup_lng'              => $data['pickup_lng'],
                'dropoff_address'         => $data['dropoff_address'],
                'dropoff_lat'             => $data['dropoff_lat'] ?? null,
                'dropoff_lng'             => $data['dropoff_lng'] ?? null,
                'distance_km'             => $data['distance_km'],
                'duration_minutes'        => $data['duration_minutes'],
                'estimated_fare'          => $data['estimated_fare'] ?? null,
                'status'                  => 'pending',
                'candidate_driver_ids'    => $candidates,
                'current_candidate_index' => 0,
                'offered_driver_id'       => $candidates[0],
                'offer_expires_at'        => now()->addSeconds(self::OFFER_TTL_SECONDS),
                // Faz 3: seçilen sürücü 30 sn içinde cevap vermezse havuza yayılır
                'pool_expand_at'          => now()->addSeconds(self::POOL_EXPAND_AFTER_SECONDS),
            ]);

            return $req->fresh();
        });
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
    public function accept(RideRequest $req, Driver $acceptingDriver): RideRequest
    {
        return DB::transaction(function () use ($req, $acceptingDriver) {
            // Atomik claim — direkt seçilen sürücü teklifi VE
            // müşterinin reconfirm onayı sonrası (havuzdaki sürücüyü accept et) iki durumu kapsar
            $statusBefore = $req->fresh()->status;

            if ($statusBefore === 'awaiting_customer_reconfirm') {
                // Reconfirm'de zaten accepted_driver_id set ve müşteri onayladı → direkt accepted'a geç
                $updated = RideRequest::where('id', $req->id)
                    ->where('status', 'awaiting_customer_reconfirm')
                    ->where('accepted_driver_id', $acceptingDriver->id)
                    ->update([
                        'status'              => 'accepted',
                        'accepted_at'         => now(),
                        'offer_expires_at'    => null,
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
                        'updated_at'          => now(),
                    ]);
            }

            if ($updated === 0) {
                throw new \RuntimeException('Bu talep artık geçerli değil (başkası aldı ya da süresi doldu).');
            }

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

            // Ride'a sürücü ata + status=assigned
            $ride->update([
                'driver_id'   => $acceptingDriver->id,
                'status'      => 'driver_arriving',
                'assigned_at' => now(),
                'confirmed_at' => now(),
            ]);

            // Driver busy
            $acceptingDriver->update(['availability_status' => 'busy']);

            $req->update(['ride_id' => $ride->id]);

            // Sistem mesajı (chat'in başına düşsün)
            RideMessage::create([
                'ride_request_id' => $req->id,
                'sender'          => 'system',
                'body'            => 'Üye sürücü yola çıktı. Tahmini varış: ' . max(1, (int) round((float) $req->distance_km * 2.4)) . ' dk.',
            ]);

            return $req->fresh();
        });
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
        }
        return $req->fresh();
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

        $req->update([
            'current_candidate_index' => $nextIndex,
            'offered_driver_id'       => $candidates[$nextIndex],
            'offer_expires_at'        => now()->addSeconds(self::OFFER_TTL_SECONDS),
        ]);
        return $req->fresh();
    }
}
