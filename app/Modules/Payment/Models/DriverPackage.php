<?php

namespace App\Modules\Payment\Models;

use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'type',
        'duration_hours',
        'price',
        'starts_at',
        'expires_at',
        'status',
        'payment_provider',
        'payment_reference',   // PayTR: merchant_oid
        'card_alias',          // PayTR: payment_type (card/wallet)
        'card_last_four',      // PayTR: masked_pan son 4 hane
        'payment_meta',
        'conversation_id',     // PayTR: get-token response token
        'paid_at',
    ];

    protected $casts = [
        'duration_hours' => 'integer',
        'price'          => 'decimal:2',
        'starts_at'      => 'datetime',
        'expires_at'     => 'datetime',
        'paid_at'        => 'datetime',
        'payment_meta'   => 'array',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function definition(): ?array
    {
        return config("packages.types.{$this->type}");
    }

    public function label(): string
    {
        return $this->definition()['label'] ?? $this->type;
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expires_at
            && $this->expires_at->isFuture();
    }
}
