<?php

namespace App\Modules\Vehicle\Models;

use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleClass extends Model
{
    use HasFactory;

    protected $table = 'vehicle_classes';

    protected $fillable = [
        'tenant_id',
        'slug',
        'name',
        'description',
        'image',
        'max_passengers',
        'max_luggage',
        'base_fare',
        'per_km_fare',
        'per_minute_fare',
        'minimum_fare',
        'boarding_fee_trusted',
        'boarding_fee_standard',
        'boarding_fee_new',
        'boarding_fee_suspicious',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_fare' => 'decimal:2',
        'per_km_fare' => 'decimal:2',
        'per_minute_fare' => 'decimal:2',
        'minimum_fare' => 'decimal:2',
        'boarding_fee_trusted' => 'decimal:2',
        'boarding_fee_standard' => 'decimal:2',
        'boarding_fee_new' => 'decimal:2',
        'boarding_fee_suspicious' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }
}
