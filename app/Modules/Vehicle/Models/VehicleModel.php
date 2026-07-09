<?php

namespace App\Modules\Vehicle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir markaya ait araç modeli (Clio, Passat, MT-07 ...).
 * category_slug: hangi sürücü kategorisinde geçerli
 * (otomobil / sari_taksi / motosiklet).
 */
class VehicleModel extends Model
{
    protected $table = 'vehicle_models';

    protected $fillable = [
        'vehicle_make_id',
        'name',
        'category_slug',
        'production_start',
        'production_end',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'production_start' => 'integer',
        'production_end'   => 'integer',
    ];

    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }

    public function scopeForCategory($query, string $categorySlug)
    {
        return $query->where('category_slug', $categorySlug);
    }

    public function scopeForMake($query, int $makeId)
    {
        return $query->where('vehicle_make_id', $makeId);
    }
}
