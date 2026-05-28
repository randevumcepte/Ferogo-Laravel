<?php

namespace App\Modules\Payment\Models;

use App\Modules\Booking\Models\NoShowReport;
use App\Modules\Booking\Models\Ride;
use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverCompensation extends Model
{
    protected $fillable = [
        'driver_id',
        'no_show_report_id',
        'ride_id',
        'reason',
        'amount',
        'currency',
        'status',
        'paid_at',
        'approved_by',
        'note',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function noShowReport(): BelongsTo
    {
        return $this->belongsTo(NoShowReport::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
