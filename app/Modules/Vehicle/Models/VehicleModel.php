<?php

namespace App\Modules\Vehicle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir markaya ait araç modeli (Clio, Passat, ...). Marka seçilince model dropdown'u doldurulur.
 */
class VehicleModel extends Model
{
    protected $table = 'vehicle_models';

    protected $fillable = [
        'vehicle_make_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }
}
