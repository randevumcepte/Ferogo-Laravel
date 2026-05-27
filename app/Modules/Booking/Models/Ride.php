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
        'estimated_distance_km',
        'estimated_duration_minutes',
        'actual_distance_km',
        'actual_duration_minutes',
        'passenger_count',
        'luggage_count',
        'base_fare',
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
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'estimated_distance_km' => 'decimal:2',
        'actual_distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'distance_fare' => 'decimal:2',
        'time_fare' => 'decimal:2',
        'extras_total' => 'decimal:2',
        'multiplier' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_fare' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'driver_arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

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
