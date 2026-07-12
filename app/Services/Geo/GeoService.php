<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Merkezi adres/konum servisi — tek beyin.
 *
 * Akış:
 *   1) suggest($q)  → yaz→öner listesi (autocomplete). Yandex açıksa Geosuggest,
 *      değilse/boşsa Photon (OSM), o da boşsa Nominatim.
 *   2) resolve($uri|$text) → seçilen önerinin koordinatı (Yandex Geocoder).
 *      Photon/Nominatim önerileri koordinatı zaten içinde taşır; onlar için
 *      resolve gerekmez (frontend lat/lon varsa direkt kullanır).
 *
 * Öğe (item) şekli — frontend sözleşmesi:
 *   ['display_name' => string, 'lat' => ?float, 'lon' => ?float,
 *    'uri' => ?string, 'provider' => 'yandex'|'photon'|'nominatim']
 *   - Yandex: uri dolu, lat/lon null (seçilince resolve gerekir)
 *   - Photon/Nominatim: lat/lon dolu, uri null
 */
class GeoService
{
    // İzmir İLİ geneli (metro değil): Aliağa/Bergama/Dikili-Çandarlı (kuzey),
    // Çeşme (batı), Ödemiş/Selçuk (güney-doğu) dahil. latMin, latMax, lonMin, lonMax
    private const IZMIR_BBOX = [37.7, 39.3, 26.0, 28.5];

    /** Yandex Geosuggest yapılandırılmış mı? */
    public function suggestEnabled(): bool
    {
        return ! empty(config('services.yandex.suggest_key'));
    }

    /** Yandex Geocoder yapılandırılmış mı? */
    public function geocoderEnabled(): bool
    {
        return ! empty(config('services.yandex.geocoder_key'));
    }

    /**
     * Autocomplete önerileri (60 dk cache).
     *
     * @return array<int, array{display_name:string, lat:?float, lon:?float, uri:?string, provider:string}>
     */
    public function suggest(string $q): array
    {
        $q = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $q)));
        if (mb_strlen($q) < 2) {
            return [];
        }

        $cacheKey = 'geo:suggest:v2:' . sha1($q);

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($q) {
            // 1) Yandex (zengin POI/işletme) — anahtar varsa
            if ($this->suggestEnabled()) {
                $r = $this->yandexSuggest($q);
                if (! empty($r)) {
                    return $r;
                }
                // Yandex boş/hata → sessizce Photon'a düş
            }

            // 2) Photon (OSM autocomplete)
            $r = $this->photonSearch($q);
            if (! empty($r)) {
                return $r;
            }

            // 3) Nominatim yedeği
            return $this->nominatimSearch($q);
        });
    }

    /**
     * Seçilen önerinin koordinatı.
     * Yandex önerisi için $uri, düz metin araması için $text ver.
     *
     * @return array{lat:float, lon:float, display_name:string}|null
     */
    public function resolve(?string $uri = null, ?string $text = null): ?array
    {
        $uri  = $uri !== null ? trim($uri) : null;
        $text = $text !== null ? trim($text) : null;

        if (($uri === null || $uri === '') && ($text === null || $text === '')) {
            return null;
        }

        $cacheKey = 'geo:resolve:v1:' . sha1(($uri ?? '') . '|' . ($text ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(1440), function () use ($uri, $text) {
            // Yandex Geocoder (uri en isabetli; yoksa metin)
            if ($this->geocoderEnabled()) {
                $r = $this->yandexGeocode($uri, $text);
                // Yalnız İzmir ili içi — dışı (Kars vb.) reddedilir.
                if ($r !== null && $this->withinIzmir($r['lat'], $r['lon'])) {
                    return $r;
                }
            }

            // Yedek: metinle Nominatim (Yandex yoksa/başarısızsa)
            if ($text !== null && $text !== '') {
                $rows = $this->nominatimSearch($text);
                if (! empty($rows)) {
                    $lat = (float) $rows[0]['lat'];
                    $lon = (float) $rows[0]['lon'];
                    if ($this->withinIzmir($lat, $lon)) {
                        return [
                            'lat'          => $lat,
                            'lon'          => $lon,
                            'display_name' => (string) $rows[0]['display_name'],
                        ];
                    }
                }
            }

            return null;
        });
    }

    /**
     * Ters geocode: koordinat → adres metni. (Alış noktası etiketi için.)
     * Sunucudan çağrılır — tarayıcı doğrudan nominatim.org'a gitmez (yavaş/rate-limit).
     * Yandex Geocoder → boşsa Nominatim. 24 saat cache (koordinat ~5 haneye yuvarlanır).
     */
    public function reverseGeocode(float $lat, float $lon): ?string
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        $rlat = round($lat, 5);
        $rlon = round($lon, 5);
        $cacheKey = 'geo:reverse:v1:' . $rlat . ',' . $rlon;

        return Cache::remember($cacheKey, now()->addMinutes(1440), function () use ($rlat, $rlon) {
            if ($this->geocoderEnabled()) {
                $r = $this->yandexReverse($rlat, $rlon);
                if ($r !== null && $r !== '') {
                    return $r;
                }
            }
            return $this->nominatimReverse($rlat, $rlon);
        });
    }

    // ------------------------------------------------------------------
    // Yandex
    // ------------------------------------------------------------------

    /** Yandex ters geocode — geocode="boylam,enlem". */
    private function yandexReverse(float $lat, float $lon): ?string
    {
        try {
            $response = Http::timeout(4)->get('https://geocode-maps.yandex.ru/1.x/', [
                'apikey'  => config('services.yandex.geocoder_key'),
                'format'  => 'json',
                'lang'    => config('services.yandex.lang', 'tr_TR'),
                'results' => 1,
                'geocode' => $lon . ',' . $lat, // Yandex: lon,lat
            ]);
            if (! $response->ok()) {
                return null;
            }
            $members = $response->json('response.GeoObjectCollection.featureMember');
            if (! is_array($members) || empty($members)) {
                return null;
            }
            $geo = $members[0]['GeoObject'] ?? [];
            $text = trim((string) ($geo['metaDataProperty']['GeocoderMetaData']['text'] ?? ''));
            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Nominatim ters geocode yedeği (sunucudan). */
    private function nominatimReverse(float $lat, float $lon): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'FerXGo/1.0 (+https://ferxgo.com.tr)',
                'Accept-Language' => 'tr,en',
            ])->timeout(3)->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'json',
                'lat'    => $lat,
                'lon'    => $lon,
                'zoom'   => 18,
            ]);
            if (! $response->ok()) {
                return null;
            }
            $text = trim((string) $response->json('display_name'));
            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Yandex Geosuggest — https://suggest-maps.yandex.ru/v1/suggest */
    private function yandexSuggest(string $q): array
    {
        try {
            $response = Http::timeout(4)->get('https://suggest-maps.yandex.ru/v1/suggest', [
                'apikey'        => config('services.yandex.suggest_key'),
                'text'          => $q,
                'lang'          => config('services.yandex.lang', 'tr_TR'),
                'results'       => 10, // max 10
                'll'            => config('services.yandex.ll'),
                'spn'           => config('services.yandex.spn'),
                'bbox'          => self::yandexBbox(), // İzmir ili sınırları
                'strict_bounds' => 1,                  // SADECE bbox içi (Kars vb. dışını getirme)
                'attrs'         => 'uri',   // seçilince Geocoder'a verilecek uri
                'print_address' => 1,       // ikincil satır (adres) için
            ]);

            if (! $response->ok()) {
                return [];
            }

            $results = $response->json('results');
            if (! is_array($results)) {
                return [];
            }

            $out = [];
            $seen = [];
            foreach ($results as $r) {
                $title = trim((string) ($r['title']['text'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $subtitle = trim((string) ($r['subtitle']['text'] ?? ''));
                if ($subtitle === '') {
                    $subtitle = trim((string) ($r['address']['formatted_address'] ?? ''));
                }
                $display = $subtitle !== '' && $subtitle !== $title
                    ? ($title . ', ' . $subtitle)
                    : $title;

                $key = mb_strtolower($display);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $out[] = [
                    'display_name' => $display,
                    'lat'          => null,
                    'lon'          => null,
                    'uri'          => (string) ($r['uri'] ?? ''),
                    'provider'     => 'yandex',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /** Yandex Geocoder — https://geocode-maps.yandex.ru/1.x/ ; uri (tercih) ya da metin. */
    private function yandexGeocode(?string $uri, ?string $text): ?array
    {
        try {
            $params = [
                'apikey'  => config('services.yandex.geocoder_key'),
                'format'  => 'json',
                'lang'    => config('services.yandex.lang', 'tr_TR'),
                'results' => 1,
            ];

            if ($uri !== null && $uri !== '') {
                $params['uri'] = $uri;
            } elseif ($text !== null && $text !== '') {
                $params['geocode'] = $text;
                $params['ll']      = config('services.yandex.ll');
                $params['spn']     = config('services.yandex.spn');
            } else {
                return null;
            }

            $response = Http::timeout(4)->get('https://geocode-maps.yandex.ru/1.x/', $params);
            if (! $response->ok()) {
                return null;
            }

            $members = $response->json('response.GeoObjectCollection.featureMember');
            if (! is_array($members) || empty($members)) {
                return null;
            }

            $geo = $members[0]['GeoObject'] ?? null;
            $pos = $geo['Point']['pos'] ?? null; // "boylam enlem" (lon lat)
            if (! is_string($pos)) {
                return null;
            }
            $parts = preg_split('/\s+/', trim($pos));
            if (count($parts) < 2) {
                return null;
            }

            $lon = (float) $parts[0];
            $lat = (float) $parts[1];

            $display = trim((string) ($geo['metaDataProperty']['GeocoderMetaData']['text'] ?? ''));
            if ($display === '') {
                $display = trim(((string) ($geo['name'] ?? '')) . ' ' . ((string) ($geo['description'] ?? '')));
            }

            return [
                'lat'          => $lat,
                'lon'          => $lon,
                'display_name' => $display,
            ];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Photon / Nominatim (OSM yedek)
    // ------------------------------------------------------------------

    /** Yandex Geosuggest/Geocoder için İzmir bbox — "lonMin,latMin~lonMax,latMax". */
    private static function yandexBbox(): string
    {
        [$latMin, $latMax, $lonMin, $lonMax] = self::IZMIR_BBOX;
        return $lonMin . ',' . $latMin . '~' . $lonMax . ',' . $latMax;
    }

    /** Koordinat İzmir ili sınırları içinde mi? (hizmet alanı dışını reddet) */
    private function withinIzmir(float $lat, float $lon): bool
    {
        [$latMin, $latMax, $lonMin, $lonMax] = self::IZMIR_BBOX;
        return $lat >= $latMin && $lat <= $latMax && $lon >= $lonMin && $lon <= $lonMax;
    }

    /** Türkçe karakter sadeleştirici (karşılaştırma için). */
    private function fold(string $s): string
    {
        $s = strtr($s, ['İ' => 'i', 'I' => 'i', 'Ş' => 's', 'Ç' => 'c', 'Ğ' => 'g', 'Ü' => 'u', 'Ö' => 'o']);
        $s = mb_strtolower($s, 'UTF-8');
        return strtr($s, ['ş' => 's', 'ç' => 'c', 'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
    }

    /** Photon (OSM autocomplete) — İzmir bias + gevşetilmiş filtreler. */
    private function photonSearch(string $q): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'FerXGo/1.0 (+https://ferxgo.com.tr)',
            ])->timeout(3)->get('https://photon.komoot.io/api/', [
                'q'     => $q,
                'lang'  => 'default',
                'lat'   => 38.4237,
                'lon'   => 27.1428,
                'limit' => 30,
            ]);
            if (! $response->ok()) {
                return [];
            }
            $features = $response->json('features');
            if (! is_array($features)) {
                return [];
            }

            $anchors = [];
            foreach (preg_split('/\s+/', $this->fold($q)) as $tok) {
                if (mb_strlen($tok) >= 3) {
                    $anchors[] = $tok;
                }
            }

            [$latMin, $latMax, $lonMin, $lonMax] = self::IZMIR_BBOX;
            $out = [];
            $seen = [];
            foreach ($features as $f) {
                $p = $f['properties'] ?? [];
                $coords = $f['geometry']['coordinates'] ?? null;
                if (! is_array($coords) || count($coords) < 2) {
                    continue;
                }

                $cc = (string) ($p['countrycode'] ?? '');
                if ($cc !== '' && $cc !== 'TR') {
                    continue;
                }

                $lat = (float) $coords[1];
                $lon = (float) $coords[0];
                if ($lat < $latMin || $lat > $latMax || $lon < $lonMin || $lon > $lonMax) {
                    continue;
                }

                $title = trim((string) ($p['name'] ?? ''));
                if ($title === '') {
                    $title = trim(((string) ($p['street'] ?? '')) . ' ' . ((string) ($p['housenumber'] ?? '')));
                }
                if ($title === '') {
                    $title = trim((string) ($p['district'] ?? $p['city'] ?? $p['locality'] ?? ''));
                }
                if ($title === '') {
                    continue;
                }

                $parts = [];
                foreach ([$p['street'] ?? null, $p['district'] ?? null, $p['city'] ?? null, $p['state'] ?? null] as $seg) {
                    $seg = trim((string) ($seg ?? ''));
                    if ($seg !== '' && $seg !== $title && ! in_array($seg, $parts, true)) {
                        $parts[] = $seg;
                    }
                }
                $secondary = implode(', ', array_slice($parts, 0, 3));
                $display = $secondary !== '' ? ($title . ', ' . $secondary) : $title;

                // Alaka skoru: sorgu kelimelerinden KAÇI sonuçta geçiyor.
                // Hiçbiri geçmiyorsa ele; geçenler skora göre sıralanır (çok eşleşen üste).
                $score = 0;
                if (! empty($anchors)) {
                    $hay = $this->fold($title . ' ' . $secondary);
                    foreach ($anchors as $a) {
                        if (str_contains($hay, $a)) {
                            $score++;
                        }
                    }
                    if ($score === 0) {
                        continue;
                    }
                }

                $key = mb_strtolower($display);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $out[] = [
                    'display_name' => $display,
                    'lat'          => $lat,
                    'lon'          => $lon,
                    'uri'          => null,
                    'provider'     => 'photon',
                    '_score'       => $score,
                ];
            }

            // Çok kelime eşleşen sonuçlar üste (PHP 8.2 usort kararlıdır → eşit skorda
            // Photon'un kendi alaka/mesafe sırası korunur). En iyi 15.
            usort($out, static fn ($a, $b) => $b['_score'] <=> $a['_score']);
            $out = array_slice($out, 0, 15);
            foreach ($out as &$row) {
                unset($row['_score']);
            }
            unset($row);

            return $out;
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /** Nominatim yedeği. */
    private function nominatimSearch(string $q): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'FerXGo/1.0 (+https://ferxgo.com.tr)',
                'Accept-Language' => 'tr,en',
            ])->timeout(3)->get('https://nominatim.openstreetmap.org/search', [
                'q'              => $q,
                'format'         => 'json',
                'addressdetails' => 0,
                'limit'          => 10,
                'countrycodes'   => 'tr',
                'viewbox'        => '26.0,39.3,28.5,37.7', // İzmir ili geneli (lon_min,lat_max,lon_max,lat_min)
                'bounded'        => 0,
            ]);
            if (! $response->ok()) {
                return [];
            }
            $rows = $response->json();
            if (! is_array($rows)) {
                return [];
            }

            return array_map(static fn ($r) => [
                'display_name' => (string) ($r['display_name'] ?? ''),
                'lat'          => (float) ($r['lat'] ?? 0),
                'lon'          => (float) ($r['lon'] ?? 0),
                'uri'          => null,
                'provider'     => 'nominatim',
            ], array_slice($rows, 0, 10));
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }
}
