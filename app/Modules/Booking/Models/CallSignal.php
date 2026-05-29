<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSignal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ride_call_id',
        'from_role',
        'type',
        'payload',
        'consumed',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'consumed'   => 'bool',
        'created_at' => 'datetime',
    ];

    public function rideCall(): BelongsTo
    {
        return $this->belongsTo(RideCall::class);
    }
}
