<?php

namespace App\Modules\Vehicle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Araç markası (Renault, Yamaha, ...). Onboarding'de marka SEÇMELİ dropdown.
 * `applicable_categories` sayesinde markanın hangi sürücü kategorilerinde
 * (otomobil/sari_taksi/motosiklet) göründüğü belirlenir.
 */
class VehicleMake extends Model
{
    protected $table = 'vehicle_makes';

    protected $fillable = [
        'name',
        'slug',
        'applicable_categories',
        'logo_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'applicable_categories' => 'array',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }

    /**
     * Sadece belirli kategoriye uygun markalar.
     */
    public function scopeForCategory($query, string $categorySlug)
    {
        return $query->whereJsonContains('applicable_categories', $categorySlug);
    }

    public function supportsCategory(string $categorySlug): bool
    {
        $cats = (array) ($this->applicable_categories ?? []);
        return in_array($categorySlug, $cats, true);
    }
}
