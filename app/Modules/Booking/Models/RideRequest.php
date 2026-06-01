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
        // ─── Yeni güvenlik / dispatcher akışı ───
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
        'pool_expand_at'                  => 'datetime',
        'pool_candidate_driver_ids'       => 'array',
        'pool_rejected_driver_ids'        => 'array',
        'pool_expanded_at'                => 'datetime',
        'reconfirm_required_at'           => 'datetime',
        'customer_reconfirmed_at'         => 'datetime',
        'customer_reconfirm_declined_at'  => 'datetime',
        'boarding_question_at'            => 'datetime',
        'boarding_confirmed_at'           => 'datetime',
        'started_at'                      => 'datetime',
        'visual_verify_prompted_at'       => 'datetime',
        'visual_verified_at'              => 'datetime',
        'visual_verify_failed_at'         => 'datetime',
        'completed_at'                    => 'datetime',
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
        return in_array($this->status, [
            'completed',
            'exhausted',
            'cancelled',
            'no_show',
            'suspended_by_incident',
        ], true);
    }

    public function isAwaitingReconfirm(): bool
    {
        return $this->status === 'awaiting_customer_reconfirm';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress' || ($this->started_at !== null && $this->completed_at === null);
    }

    public function securityIncidents(): HasMany
    {
        return $this->hasMany(\App\Modules\Security\Models\SecurityIncident::class);
    }

    public function panicAlerts(): HasMany
    {
        return $this->hasMany(\App\Modules\Security\Models\PanicAlert::class);
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
