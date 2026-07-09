<?php

namespace App\Modules\Booking\Services;

use App\Models\User;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideExtra;
use App\Modules\Pricing\Services\FareCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(private FareCalculator $calculator) {}

    /**
     * @param array{
     *   city_id:int,
     *   vehicle_class_id:int,
     *   customer_name:string,
     *   customer_phone:string,
     *   customer_tc_no?:?string,
     *   pickup_address:string,
     *   pickup_lat?:float,
     *   pickup_lng?:float,
     *   pickup_notes?:?string,
     *   dropoff_address:string,
     *   dropoff_lat?:float,
     *   dropoff_lng?:float,
     *   dropoff_notes?:?string,
     *   passenger_count?:int,
     *   luggage_count?:int,
     *   scheduled_at:string,
     *   transport_type?:?string,
     *   transport_code?:?string,
     *   transport_scheduled_at?:?string,
     *   distance_km?:float,
     *   duration_minutes?:int,
     *   extras?:array,
     *   source?:string
     * } $data
     */
    public function create(array $data): Ride
    {
        return DB::transaction(function () use ($data) {
            $scheduledAt = isset($data['scheduled_at'])
                ? Carbon::parse($data['scheduled_at'])
                : null;

            $normalizedPhone = $this->normalizePhone($data['customer_phone']);
            $trustTier = $this->calculator->resolveTierForPhone($normalizedPhone);

            $fare = $this->calculator->calculate(
                cityId: (int) $data['city_id'],
                vehicleClassId: (int) $data['vehicle_class_id'],
                distanceKm: (float) ($data['distance_km'] ?? 10),
                durationMinutes: (int) ($data['duration_minutes'] ?? 20),
                extras: $data['extras'] ?? [],
                scheduledAt: $scheduledAt,
                customerTrustTier: $trustTier,
            );

            // Martı modeli: sistem yalnızca ÖNERİ üretir; nihai yolculuk paylaşım tutarını
            // yolcu belirler. Yolcu bir tutar girdiyse total_fare onun değeridir; sistemin
            // hesabı yalnızca başlangıç önerisi (breakdown kayıt için tutulur).
            $suggestedTotal = (float) $fare['total_fare'];
            $offeredFare = isset($data['offered_fare']) && $data['offered_fare'] !== null && $data['offered_fare'] !== ''
                ? round((float) $data['offered_fare'], 2)
                : $suggestedTotal;

            $customer = $this->resolveCustomer($data);

            // ─── Karşılama (uçak/tren/otogar) — Faz 1 ───
            $transportType = in_array($data['transport_type'] ?? null, Ride::TRANSPORT_TYPES, true)
                ? $data['transport_type']
                : null;
            $transportScheduledAt = ($transportType && ! empty($data['transport_scheduled_at']))
                ? Carbon::parse($data['transport_scheduled_at'])
                : null;
            // Tampon süre tipe göre otomatik (yolcu değil sistem belirler).
            $freeWaitMinutes = $transportType
                ? (Ride::FREE_WAIT_DEFAULTS[$transportType] ?? null)
                : null;

            $ride = Ride::create([
                'city_id' => $data['city_id'],
                'vehicle_class_id' => $data['vehicle_class_id'],
                'customer_user_id' => $customer->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $normalizedPhone,
                'customer_tc_no' => $data['customer_tc_no'] ?? null,
                'pickup_address' => $data['pickup_address'],
                'pickup_lat' => $data['pickup_lat'] ?? 0,
                'pickup_lng' => $data['pickup_lng'] ?? 0,
                'pickup_notes' => $data['pickup_notes'] ?? null,
                'dropoff_address' => $data['dropoff_address'],
                'dropoff_lat' => $data['dropoff_lat'] ?? 0,
                'dropoff_lng' => $data['dropoff_lng'] ?? 0,
                'dropoff_notes' => $data['dropoff_notes'] ?? null,
                'transport_type' => $transportType,
                'transport_code' => $transportType ? ($data['transport_code'] ?? null) : null,
                'transport_scheduled_at' => $transportScheduledAt,
                'free_wait_minutes' => $freeWaitMinutes,
                'passenger_count' => $data['passenger_count'] ?? 1,
                'luggage_count' => $data['luggage_count'] ?? 0,
                'scheduled_at' => $scheduledAt,
                'estimated_distance_km' => $data['distance_km'] ?? null,
                'estimated_duration_minutes' => $data['duration_minutes'] ?? null,
                'base_fare' => $fare['base_fare'],
                'boarding_fee' => $fare['boarding_fee'],
                'customer_trust_tier' => $fare['customer_trust_tier'],
                'distance_fare' => $fare['distance_fare'],
                'time_fare' => $fare['time_fare'],
                'extras_total' => $fare['extras_total'],
                'multiplier' => $fare['multiplier'],
                'subtotal' => $fare['subtotal'],
                'total_fare' => $offeredFare,
                'currency' => 'TRY',
                'source' => $data['source'] ?? 'web',
                'status' => 'pending',
            ]);

            // Ekstraları kaydet
            foreach ($fare['extras'] as $extraData) {
                RideExtra::create([
                    'ride_id' => $ride->id,
                    'extra_id' => $extraData['extra_id'],
                    'quantity' => $extraData['quantity'],
                    'unit_price' => $extraData['unit_price'],
                    'total_price' => $extraData['total_price'],
                ]);
            }

            return $ride->fresh(['extras.extra', 'city', 'vehicleClass']);
        });
    }

    /**
     * Telefon numarasından müşteri kullanıcı bul veya yarat (anonim rezervasyon).
     * Email zorunlu olduğu için synthetic email üretiyoruz.
     */
    protected function resolveCustomer(array $data): User
    {
        $phone = $this->normalizePhone($data['customer_phone']);

        // Mevcut sessionda login bir müşteri varsa ve telefonu eşleşiyorsa onu kullan.
        // MÜŞTERİ guard'ı kullan (sürücü guard'dan bağımsız).
        $authed = Auth::guard('customer')->user();
        if ($authed && $authed->type === 'customer' && $authed->phone === $phone) {
            return $authed;
        }

        return User::firstOrCreate(
            ['phone' => $phone],
            [
                'name'              => $data['customer_name'],
                'email'             => 'c' . $phone . '@ferogo.local',
                'password'          => bcrypt(Str::random(40)),
                'type'              => 'customer',
                'status'            => 'active',
                'tc_no'             => $data['customer_tc_no'] ?? null,
                'phone_verified_at' => now(),
            ]
        );
    }

    /**
     * Telefon normalizasyonu — CustomerTrustService ile birebir aynı kural:
     * "+90 532 ...", "0532 ...", "5321234567" → "5321234567"
     * (90 ve 0 prefix'leri at; 10 haneye düş)
     */
    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }
        return $digits;
    }
}
