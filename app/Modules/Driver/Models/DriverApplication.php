<?php

namespace App\Modules\Driver\Models;

use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\VehicleMake;
use App\Modules\Vehicle\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'city_id',
        'birth_year',
        'gender',
        'license_class',
        'driver_category_id',
        'experience_band',
        'has_src',
        'has_vehicle',
        'vehicle_info',
        'vehicle_make_id',
        'vehicle_model_id',
        'vehicle_year',
        'vehicle_color',
        'notes',
        'status',
        'source',
        'ip_address',
    ];

    protected $casts = [
        'has_src'          => 'boolean',
        'has_vehicle'      => 'boolean',
        'birth_year'       => 'integer',
        'vehicle_year'     => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DriverCategory::class, 'driver_category_id');
    }

    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }
}
