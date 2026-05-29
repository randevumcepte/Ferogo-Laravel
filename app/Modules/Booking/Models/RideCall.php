<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RideCall extends Model
{
    protected $fillable = [
        'ride_request_id',
        'initiator',
        'status',
        'started_at',
        'accepted_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'accepted_at' => 'datetime',
        'ended_at'    => 'datetime',
    ];

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(CallSignal::class);
    }
}
