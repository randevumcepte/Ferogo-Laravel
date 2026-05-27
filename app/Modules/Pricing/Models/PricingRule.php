<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Shared\Models\City;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'city_id',
        'vehicle_class_id',
        'base_fare',
        'per_km_fare',
        'per_minute_fare',
        'minimum_fare',
        'night_multiplier',
        'night_start',
        'night_end',
        'peak_multiplier',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_fare' => 'decimal:2',
        'per_km_fare' => 'decimal:2',
        'per_minute_fare' => 'decimal:2',
        'minimum_fare' => 'decimal:2',
        'night_multiplier' => 'decimal:2',
        'peak_multiplier' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class);
    }
}
