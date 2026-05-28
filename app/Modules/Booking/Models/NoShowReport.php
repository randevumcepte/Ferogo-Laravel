<?php

namespace App\Modules\Booking\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoShowReport extends Model
{
    protected $fillable = [
        'ride_id',
        'ride_request_id',
        'driver_id',
        'customer_phone',
        'resolution',
        'reported_lat',
        'reported_lng',
        'pickup_lat',
        'pickup_lng',
        'distance_from_pickup_m',
        'wait_seconds',
        'compensation_amount',
        'compensation_paid_at',
        'driver_note',
        'admin_note',
    ];

    protected $casts = [
        'reported_lat'           => 'decimal:7',
        'reported_lng'           => 'decimal:7',
        'pickup_lat'             => 'decimal:7',
        'pickup_lng'             => 'decimal:7',
        'distance_from_pickup_m' => 'decimal:2',
        'compensation_amount'    => 'decimal:2',
        'compensation_paid_at'   => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }
}
