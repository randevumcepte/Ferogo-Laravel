<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_request_id',
        'sender',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }
}
