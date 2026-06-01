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
        'payment_reference',
        'card_token',
        'card_alias',
        'card_last_four',
        'payment_meta',
        'three_ds_html',
        'conversation_id',
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

    protected $hidden = [
        // 3D Secure HTML payload genelde 100KB+ olur, accidentally JSON'a sızmasın
        'three_ds_html',
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
