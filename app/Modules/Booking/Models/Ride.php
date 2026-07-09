<?php

namespace App\Modules\Booking\Models;

use App\Models\User;
use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Models\Payment;
use App\Modules\Shared\Models\City;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ride extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_user_id',
        'driver_id',
        'vehicle_id',
        'vehicle_class_id',
        'city_id',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'pickup_notes',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_notes',
        // ─── Karşılama (uçak/tren/otogar) — Faz 1 ───
        'transport_type',
        'transport_code',
        'transport_scheduled_at',
        'free_wait_minutes',
        'pax_status',
        'pax_status_note',
        'pax_status_at',
        'estimated_distance_km',
        'estimated_duration_minutes',
        'actual_distance_km',
        'actual_duration_minutes',
        'passenger_count',
        'luggage_count',
        'base_fare',
        'boarding_fee',
        'customer_trust_tier',
        'distance_fare',
        'time_fare',
        'extras_total',
        'multiplier',
        'subtotal',
        'discount',
        'total_fare',
        'currency',
        'status',
        'source',
        'scheduled_at',
        'confirmed_at',
        'assigned_at',
        'driver_arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'customer_name',
        'customer_phone',
        'customer_tc_no',
        'customer_rating',
        'customer_review',
        'driver_rating',
        'driver_review',
        // ─── Rezervasyon dispatcher ───
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
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'estimated_distance_km' => 'decimal:2',
        'actual_distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'boarding_fee' => 'decimal:2',
        'distance_fare' => 'decimal:2',
        'time_fare' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'multiplier' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_fare' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'transport_scheduled_at' => 'datetime',
        'pax_status_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'driver_arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        // ─── Rezervasyon dispatcher ───
        'pool_published_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_driver_ids' => 'array',
        'reconfirm_requested_at' => 'datetime',
        'reconfirm_deadline_at' => 'datetime',
        'driver_reconfirmed_at' => 'datetime',
        'imminent_notified_at' => 'datetime',
        'masked_call_unlocked_at' => 'datetime',
        'prepayment_authorized' => 'boolean',
    ];

    // ─── Rezervasyon status sabitleri ───
    public const STATUS_RES_POOL          = 'reservation_pending_pool';
    public const STATUS_RES_ACCEPTED      = 'reservation_accepted';
    public const STATUS_RES_RECONFIRM_REQ = 'reservation_reconfirm_requested';
    public const STATUS_RES_CONFIRMED     = 'reservation_confirmed';
    public const STATUS_RES_IMMINENT      = 'reservation_imminent';
    public const STATUS_RES_UNMATCHED     = 'reservation_unmatched';

    public const RESERVATION_STATUSES = [
        self::STATUS_RES_POOL,
        self::STATUS_RES_ACCEPTED,
        self::STATUS_RES_RECONFIRM_REQ,
        self::STATUS_RES_CONFIRMED,
        self::STATUS_RES_IMMINENT,
    ];

    // ─── Karşılama (uçak/tren/otogar) sabitleri — Faz 1 ───
    public const TRANSPORT_FLIGHT = 'flight';
    public const TRANSPORT_TRAIN  = 'train';
    public const TRANSPORT_BUS    = 'bus';

    public const TRANSPORT_TYPES = [
        self::TRANSPORT_FLIGHT,
        self::TRANSPORT_TRAIN,
        self::TRANSPORT_BUS,
    ];

    /** Ulaşım tipine göre varsayılan ücretsiz bekleme (tampon) süresi — dakika. */
    public const FREE_WAIT_DEFAULTS = [
        self::TRANSPORT_FLIGHT => 45, // bagaj + pasaport/çıkış payı
        self::TRANSPORT_TRAIN  => 25,
        self::TRANSPORT_BUS    => 20,
    ];

    /** Yolcu → şoför canlı sinyal durumları. */
    public const PAX_ON_WAY  = 'on_way';
    public const PAX_ARRIVED = 'arrived';
    public const PAX_DELAYED = 'delayed';
    public const PAX_STATUSES = [self::PAX_ON_WAY, self::PAX_ARRIVED, self::PAX_DELAYED];

    /** Bir karşılama (uçak/tren/otogar) rezervasyonu mu? */
    public function isMeeting(): bool
    {
        return in_array($this->transport_type, self::TRANSPORT_TYPES, true);
    }

    /** Ücretsiz beklemenin bittiği an: planlanan varış + tampon süre. */
    public function freeWaitUntil(): ?\Illuminate\Support\Carbon
    {
        if (! $this->transport_scheduled_at || ! $this->free_wait_minutes) {
            return null;
        }
        return $this->transport_scheduled_at->copy()->addMinutes((int) $this->free_wait_minutes);
    }

    public function transportLabel(): ?string
    {
        return [
            self::TRANSPORT_FLIGHT => 'Uçak',
            self::TRANSPORT_TRAIN  => 'Tren',
            self::TRANSPORT_BUS    => 'Otobüs',
        ][$this->transport_type] ?? null;
    }

    public function transportIcon(): ?string
    {
        return [
            self::TRANSPORT_FLIGHT => '✈️',
            self::TRANSPORT_TRAIN  => '🚆',
            self::TRANSPORT_BUS    => '🚌',
        ][$this->transport_type] ?? null;
    }

    public function paxStatusLabel(): ?string
    {
        return [
            self::PAX_ON_WAY  => 'Yola çıktı',
            self::PAX_ARRIVED => 'Geldi, bekliyor',
            self::PAX_DELAYED => 'Gecikecek',
        ][$this->pax_status] ?? null;
    }

    /** Bir rezervasyon yaşam döngüsünde mi? */
    public function isReservation(): bool
    {
        return in_array($this->status, self::RESERVATION_STATUSES, true)
            || $this->status === self::STATUS_RES_UNMATCHED;
    }

    /** Sürücü-müşteri arası chat açık olsun mu? (kabul edildiyse) */
    public function chatUnlocked(): bool
    {
        return $this->driver_id !== null
            && in_array($this->status, [
                self::STATUS_RES_ACCEPTED,
                self::STATUS_RES_RECONFIRM_REQ,
                self::STATUS_RES_CONFIRMED,
                self::STATUS_RES_IMMINENT,
                'assigned', 'driver_arriving', 'in_progress',
            ], true);
    }

    /** Maskeli arama açık mı? (T-2h imminent sonrası) */
    public function callUnlocked(): bool
    {
        return $this->masked_call_unlocked_at !== null
            && $this->masked_call_unlocked_at->isPast();
    }

    protected static function booted(): void
    {
        static::creating(function (self $ride) {
            if (empty($ride->public_id)) {
                $ride->public_id = (string) Str::ulid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(RideExtra::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
