<?php

namespace App\Modules\Shared\Models;

use App\Modules\Booking\Models\Ride;
use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'country_code',
        'center_lat',
        'center_lng',
        'timezone',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }
}
