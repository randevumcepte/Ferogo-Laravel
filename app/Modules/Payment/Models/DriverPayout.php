<?php

namespace App\Modules\Payment\Models;

use App\Modules\Driver\Models\Driver;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DriverPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'driver_id',
        'period_start',
        'period_end',
        'total_rides',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'currency',
        'status',
        'payment_reference',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payout) {
            if (empty($payout->public_id)) {
                $payout->public_id = (string) Str::ulid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
