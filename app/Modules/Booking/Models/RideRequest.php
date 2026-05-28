<?php

namespace App\Modules\Booking\Models;

use App\Modules\Driver\Models\Driver;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RideRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'customer_name',
        'customer_phone',
        'phone_verified_at',
        'verification_token',
        'client_ip',
        'client_fingerprint',
        'user_agent',
        'vehicle_class_id',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'duration_minutes',
        'estimated_fare',
        'status',
        'candidate_driver_ids',
        'current_candidate_index',
        'offered_driver_id',
        'offer_expires_at',
        'accepted_at',
        'accepted_driver_id',
        'ride_id',
        'rejection_count',
        'driver_arrived_at',
        'customer_confirmed_at',
        'no_show_at',
        'captcha_passed',
    ];

    protected $casts = [
        'pickup_lat'            => 'decimal:7',
        'pickup_lng'            => 'decimal:7',
        'dropoff_lat'           => 'decimal:7',
        'dropoff_lng'           => 'decimal:7',
        'distance_km'           => 'decimal:2',
        'estimated_fare'        => 'decimal:2',
        'candidate_driver_ids'  => 'array',
        'offer_expires_at'      => 'datetime',
        'accepted_at'           => 'datetime',
        'phone_verified_at'     => 'datetime',
        'driver_arrived_at'     => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'no_show_at'            => 'datetime',
        'captcha_passed'        => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $req) {
            if (empty($req->public_id)) {
                $req->public_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class);
    }

    public function offeredDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'offered_driver_id');
    }

    public function acceptedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'accepted_driver_id');
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RideMessage::class)->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['accepted', 'exhausted', 'cancelled', 'no_show'], true);
    }

    public function offerExpired(): bool
    {
        return $this->offer_expires_at !== null && $this->offer_expires_at->isPast();
    }

    public function secondsRemaining(): int
    {
        if (! $this->offer_expires_at) return 0;
        return max(0, $this->offer_expires_at->diffInSeconds(now(), false) * -1);
    }
}
