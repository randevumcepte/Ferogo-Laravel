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
        'class_confirmed_at',
        'vehicle_type',
        'vehicle_make_id',
        'vehicle_model_id',
        'brand',
        'model',
        'year_of_manufacture',
        'color',
        'capacity',
        'plate',
        'registration_file_path',
        'registration_approved_at',
        'insurance_policy',
        'insurance_expires_at',
        'inspection_expires_at',
        'license_expires_at',
        'has_baby_seat',
        'has_child_seat',
        'has_booster_seat',
        'pet_friendly',
        'photos',
        'photo_angles',
        'status',
    ];

    protected $casts = [
        'class_confirmed_at' => 'datetime',
        'registration_approved_at' => 'datetime',
        'insurance_expires_at' => 'date',
        'inspection_expires_at' => 'date',
        'license_expires_at' => 'date',
        'has_baby_seat' => 'boolean',
        'has_child_seat' => 'boolean',
        'has_booster_seat' => 'boolean',
        'pet_friendly' => 'boolean',
        'photos' => 'array',
        'photo_angles' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class);
    }

    // NOT: 'brand' ve 'model' string sütunları olduğu için ilişki adları
    // çakışmayacak şekilde vehicleMake / vehicleModel seçildi.
    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }
}
