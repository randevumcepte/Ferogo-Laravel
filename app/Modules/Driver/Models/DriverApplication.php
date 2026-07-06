<?php

namespace App\Modules\Driver\Models;

use App\Modules\Shared\Models\City;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'city_id',
        'birth_year',
        'gender',
        'license_class',
        'experience_band',
        'has_src',
        'has_vehicle',
        'vehicle_info',
        'notes',
        'status',
        'source',
        'ip_address',
    ];

    protected $casts = [
        'has_src' => 'boolean',
        'has_vehicle' => 'boolean',
        'birth_year' => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
