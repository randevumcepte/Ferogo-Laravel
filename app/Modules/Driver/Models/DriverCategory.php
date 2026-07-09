<?php

namespace App\Modules\Driver\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sürücü kategorisi: Otomobil, Sarı Taksi, Motosiklet.
 * Yasal olarak farklı ehliyet, farklı belge gereksinimleri var.
 */
class DriverCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'emoji',
        'description',
        'required_license_class',
        'requires_src',
        'requires_helmet',
        'required_documents',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'requires_src'        => 'boolean',
        'requires_helmet'     => 'boolean',
        'is_active'           => 'boolean',
        'required_documents'  => 'array',
        'sort_order'          => 'integer',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(DriverApplication::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /**
     * Kategori slugu ile hızlı erişim.
     */
    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * "🚗 Otomobil" gibi başlık.
     */
    public function displayLabel(): string
    {
        return trim(($this->emoji ?? '') . ' ' . $this->name);
    }
}
