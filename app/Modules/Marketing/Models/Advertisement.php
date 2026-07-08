<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Reklam / Sponsorluk alanı.
 *
 * Sunumdaki "REKLAM ALANLARI" slaytının veri karşılığı. Her uygulama slotu (placement)
 * için birden çok aktif reklam olabilir; gösterimde ağırlıklı ROTASYON uygulanır
 * (share of voice). is_exclusive=true olan reklam o slotta TEK gösterilir (tekellik/takeover).
 * Süper admin panelinden yönetilir.
 */
class Advertisement extends Model
{
    use HasFactory;

    /** Uygulamadaki reklam slotları → görünen etiket */
    public const PLACEMENTS = [
        'home_banner'            => 'Ana Sayfa Banner',
        'ride_tracking'          => 'Yolculuk Takip (Platin)',
        'radar_map'              => 'Radar / Harita',
        'radar_sidebar'          => 'Radar Sürücü Listesi Altı',
        'driver_apply'           => 'Sürücü Başvuru (Banner)',
        'driver_apply_bottom'    => 'Sürücü Olun — Alt Reklam Alanı',
        'driver_panel'           => 'Sürücü Paneli',
        'popup'                  => 'Açılır Pencere (Popup)',
    ];

    /** Slot segment/açıklaması (boş alan görselinde gösterilir) */
    public const PLACEMENT_SEGMENTS = [
        'home_banner'            => 'Standart · tüm sektörler',
        'ride_tracking'          => 'Platin · esir dikkat anı',
        'radar_map'              => 'Orta segment',
        'radar_sidebar'          => 'Sürücü listesi yanı · sürekli görünür',
        'driver_apply'           => 'Sürücü başvuru sayfası · geniş banner',
        'driver_apply_bottom'    => 'Sürücü başvuru · form altı',
        'driver_panel'           => 'Gün boyu açık',
        'popup'                  => 'Tüm sayfalar · açılır pencere',
    ];

    /**
     * Her alan için ÖNERİLEN görsel ölçüsü (px). "Tam görsel" modunda görsel
     * kırpılmadan tüm alanı kaplar; bu ölçüler en iyi görünüm içindir.
     */
    public const PLACEMENT_DIMENSIONS = [
        'home_banner'            => '1200 × 400 px (3:1 yatay)',
        'ride_tracking'          => '1200 × 400 px (3:1 yatay)',
        'radar_map'              => '1200 × 400 px (3:1 yatay)',
        'radar_sidebar'          => '800 × 800 px (kare)',
        'driver_apply'           => '1200 × 400 px (3:1 yatay)',
        'driver_apply_bottom'    => '1200 × 400 px (3:1 yatay)',
        'driver_panel'           => '1200 × 400 px (3:1 yatay)',
        'popup'                  => '1000 × 1000 px (kare)',
    ];

    public static function dimensionsFor(?string $placement): string
    {
        return self::PLACEMENT_DIMENSIONS[$placement] ?? '1200 × 400 px';
    }

    /** Hedef sektörler (sunum: Slayt 12) */
    public const SECTORS = [
        'sigorta'          => 'Sigorta',
        'otomotiv'         => 'Otomotiv / Bayi',
        'insaat_emlak'     => 'İnşaat / Emlak',
        'akaryakit_lastik' => 'Akaryakıt / Lastik / Servis',
        'banka_finans'     => 'Banka / Finans',
        'yerel'            => 'Yerel (Restoran / AVM / Klinik)',
        'diger'            => 'Diğer',
    ];

    protected $fillable = [
        'tenant_id',
        'placement',
        'sector',
        'title',
        'sponsor_name',
        'description',
        'image_url',
        'image_only',
        'link_url',
        'cta_text',
        'is_active',
        'sort_order',
        'rotation_weight',
        'is_exclusive',
        'starts_at',
        'ends_at',
        'impressions',
        'clicks',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'image_only' => 'boolean',
        'is_exclusive' => 'boolean',
        'rotation_weight' => 'integer',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'impressions' => 'integer',
        'clicks'      => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Yayında olan (aktif + tarih penceresi içinde) reklamlar */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * Bir slot için gösterilecek aktif reklam (yoksa null → boş alan gösterilir).
     *
     * Kural:
     *  1. O slotta TEKELLİK (is_exclusive) reklamı varsa → rotasyon yok, o gösterilir.
     *     (Tekellik / Takeover / Ana Sponsor paketleri bu şekilde çalışır.)
     *  2. Aksi halde tüm aktif reklamlar arasında rotation_weight ile AĞIRLIKLI RASTGELE
     *     bir reklam gösterilir → her sayfa açılışında sıra döner (share of voice).
     */
    public static function activeFor(string $placement): ?self
    {
        $ads = static::query()
            ->where('placement', $placement)
            ->live()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        if ($ads->isEmpty()) {
            return null;
        }

        // 1) Tekellik varsa rotasyona sokmadan onu göster
        $exclusive = $ads->firstWhere('is_exclusive', true);
        if ($exclusive) {
            return $exclusive;
        }

        // 2) Ağırlıklı rastgele seçim (rotasyon / share of voice)
        $totalWeight = (int) $ads->sum(fn (self $ad) => max(1, (int) $ad->rotation_weight));
        if ($totalWeight <= 0) {
            return $ads->first();
        }

        $pick = random_int(1, $totalWeight);
        $acc = 0;
        foreach ($ads as $ad) {
            $acc += max(1, (int) $ad->rotation_weight);
            if ($pick <= $acc) {
                return $ad;
            }
        }

        return $ads->first();
    }

    /** Bir slottaki aktif reklam sayısı (admin/rapor için) */
    public static function rotationCountFor(string $placement): int
    {
        return static::query()->where('placement', $placement)->live()->count();
    }

    /**
     * Gösterilecek görselin gerçek URL'i.
     * image_url ya harici bir URL (http…) ya da 'ads' diskine yüklenmiş dosyanın
     * göreli yolu olabilir; her iki durumu da tam URL'e çevirir.
     */
    public function getImageSrcAttribute(): ?string
    {
        $value = $this->image_url;

        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('ads')->url($value);
    }

    public function placementLabel(): string
    {
        return self::PLACEMENTS[$this->placement] ?? $this->placement;
    }

    public function placementSegment(): string
    {
        return self::PLACEMENT_SEGMENTS[$this->placement] ?? '';
    }

    /** Gösterim sayacını artır (blade render'ında çağrılır, modeli kirletmez) */
    public function recordImpression(): void
    {
        static::query()->whereKey($this->getKey())->increment('impressions');
    }
}
