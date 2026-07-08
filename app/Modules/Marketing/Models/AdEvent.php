<?php

namespace App\Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Reklam olay kaydı (gösterim/tıklama). Detaylı raporların kaynağı.
 * AdEvent::record(...) çağrısı istekten bağlam (cihaz, kitle, ilçe, IP-hash) çıkarır.
 */
class AdEvent extends Model
{
    protected $fillable = [
        'advertisement_id', 'placement', 'type', 'occurred_at', 'hour', 'dow',
        'city', 'district', 'lat', 'lng', 'device', 'audience', 'user_id', 'anon_id', 'ip_hash',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(Advertisement::class);
    }

    /**
     * Tekil ziyaretçi kimliği (çerez). Varsa mevcut değeri, yoksa yeni UUID üretir.
     * Döner: [anonId, cookie] — cookie response'a ->withCookie() ile eklenir.
     */
    public static function anonId(Request $request): array
    {
        $id = $request->cookie('ferxgo_aid');
        if (! is_string($id) || $id === '' || strlen($id) > 40) {
            $id = (string) Str::uuid();
        }
        $cookie = cookie('ferxgo_aid', $id, 60 * 24 * 365); // 1 yıl
        return [$id, $cookie];
    }

    /** İzmir ilçe merkezleri (kaba) — koordinattan en yakın ilçeye eşleme için. */
    public const IZMIR_DISTRICTS = [
        'Konak' => [38.418, 27.128], 'Karşıyaka' => [38.461, 27.109], 'Bornova' => [38.470, 27.220],
        'Buca' => [38.379, 27.170], 'Bayraklı' => [38.462, 27.170], 'Çiğli' => [38.497, 27.070],
        'Gaziemir' => [38.320, 27.120], 'Karabağlar' => [38.380, 27.100], 'Balçova' => [38.390, 27.050],
        'Narlıdere' => [38.395, 27.000], 'Güzelbahçe' => [38.360, 26.880], 'Menemen' => [38.610, 27.070],
        'Aliağa' => [38.800, 26.970], 'Foça' => [38.670, 26.760], 'Torbalı' => [38.155, 27.360],
        'Kemalpaşa' => [38.430, 27.420], 'Menderes' => [38.250, 27.130], 'Seferihisar' => [38.196, 26.840],
        'Urla' => [38.323, 26.764], 'Çeşme' => [38.323, 26.303], 'Karaburun' => [38.638, 26.512],
        'Dikili' => [39.070, 26.890], 'Bergama' => [39.120, 27.180], 'Kınık' => [39.090, 27.380],
        'Ödemiş' => [38.230, 27.970], 'Tire' => [38.090, 27.735], 'Bayındır' => [38.220, 27.650],
        'Kiraz' => [38.230, 28.200], 'Beydağ' => [38.085, 28.210], 'Selçuk' => [37.950, 27.370],
    ];

    /** Koordinattan en yakın İzmir ilçesi (İzmir dışıysa ~) → null. */
    public static function districtFromLatLng(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }
        $best = null;
        $bestD = INF;
        foreach (self::IZMIR_DISTRICTS as $name => [$dlat, $dlng]) {
            $d = ($lat - $dlat) ** 2 + ($lng - $dlng) ** 2;
            if ($d < $bestD) {
                $bestD = $d;
                $best = $name;
            }
        }
        // İzmir kabaca 37.5–39.4 lat, 26.2–28.4 lng dışındaysa ilçe atama
        if ($lat < 37.3 || $lat > 39.5 || $lng < 26.0 || $lng > 28.6) {
            return null;
        }
        return $best;
    }

    /**
     * Bir gösterim/tıklama olayını kaydeder. İstekten bağlamı otomatik çıkarır.
     * $anonId route'ta çerezden okunur/üretilir ve buraya geçilir.
     */
    public static function record(
        Advertisement $ad,
        string $type,
        Request $request,
        ?float $lat = null,
        ?float $lng = null,
        ?string $anonId = null
    ): void {
        $ua = (string) ($request->userAgent() ?? '');

        // Cihaz
        if (str_contains($ua, 'Ferxgo') || str_contains($ua, 'Ferogo') || $request->hasHeader('X-Ferxgo-App')) {
            $device = 'app';
        } elseif (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua)) {
            $device = 'mobile';
        } else {
            $device = 'desktop';
        }

        // Kitle (guard'lar)
        $audience = 'guest';
        $userId = null;
        if (auth('driver')->check()) {
            $audience = 'driver';
            $userId = auth('driver')->id();
        } elseif (auth('customer')->check()) {
            $audience = 'customer';
            $userId = auth('customer')->id();
        }

        $now = now();
        $district = self::districtFromLatLng($lat, $lng);

        self::create([
            'advertisement_id' => $ad->getKey(),
            'placement' => $ad->placement,
            'type' => $type,
            'occurred_at' => $now,
            'hour' => (int) $now->format('G'),
            'dow' => (int) $now->format('w'),
            'city' => $district ? 'İzmir' : null,
            'district' => $district,
            'lat' => $lat,
            'lng' => $lng,
            'device' => $device,
            'audience' => $audience,
            'user_id' => $userId,
            'anon_id' => $anonId,
            'ip_hash' => hash('sha256', ($request->ip() ?? '') . config('app.key')),
        ]);
    }
}
