<?php

namespace App\Modules\Legal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hukuki metnin versiyonlu kaydı.
 *
 * Metin değiştiğinde yeni versiyon eklenir; eski versiyon `superseded_at` ile
 * pasifleşir ama silinmez. Aktif versiyon = `superseded_at IS NULL`.
 */
class LegalTextVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'version',
        'content',
        'sha256',
        'published_at',
        'superseded_at',
        'title',
        'change_notes',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function consents(): HasMany
    {
        return $this->hasMany(LegalConsent::class, 'text_version_id');
    }

    /**
     * Belirli bir metnin aktif (geçerli) versiyonunu döner.
     */
    public static function currentFor(string $key): ?self
    {
        return static::query()
            ->where('key', $key)
            ->whereNull('superseded_at')
            ->orderByDesc('published_at')
            ->first();
    }

    /**
     * Verilen içeriğin SHA-256 hash'i.
     */
    public static function hashContent(string $content): string
    {
        return hash('sha256', $content);
    }
}
