<?php

namespace App\Modules\Vehicle\Models;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'vehicle_class_id',
        'brand',
        'model',
        'year_of_manufacture',
        'color',
        'plate',
        'insurance_policy',
        'insurance_expires_at',
        'inspection_expires_at',
        'license_expires_at',
        'has_baby_seat',
        'has_child_seat',
        'has_booster_seat',
        'pet_friendly',
        'photos',
        'status',
    ];

    protected $casts = [
        'insurance_expires_at' => 'date',
        'inspection_expires_at' => 'date',
        'license_expires_at' => 'date',
        'has_baby_seat' => 'boolean',
        'has_child_seat' => 'boolean',
        'has_booster_seat' => 'boolean',
        'pet_friendly' => 'boolean',
        'photos' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class);
    }
}
