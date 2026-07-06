<?php

namespace App\Modules\Booking\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pazarlık geçmişi — her teklif/karşı teklif/kabul/ret adımının denetim kaydı.
 * Uyuşmazlık, destek ve KVKK için değişmez log.
 */
class RidePriceOffer extends Model
{
    protected $fillable = [
        'ride_request_id',
        'driver_id',
        'actor',   // customer | driver | system
        'type',    // offer | counter | accept | reject
        'amount',
        'round',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'round'  => 'integer',
    ];

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
