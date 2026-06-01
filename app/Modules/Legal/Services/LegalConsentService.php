<?php

namespace App\Modules\Legal\Services;

use App\Modules\Legal\Models\LegalConsent;
use App\Modules\Legal\Models\LegalTextVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hukuki onayları sunucu tarafında kalıcı olarak kaydeder.
 *
 * Akışlar:
 *   1) Modal'da "Anladım, devam et" → record('platform_notice')
 *   2) Sürücü kayıt formu → record() × ['terms', 'kvkk', 'ride_sharing', 'driver_registration']
 *   3) Yolcu rezervasyon formu → record() × ['kvkk', 'distance_sales']
 *   4) OTP doğrulandığında → identifyByPhone() — anonim consent'lere telefon ekler
 */
class LegalConsentService
{
    /**
     * Tek bir consent kaydı oluşturur.
     *
     * @param  string  $consentType  'platform_notice', 'terms', 'kvkk', vs.
     * @param  string|null  $textKey  Aktif versiyon hangi key altından çekilsin (null ise consentType ile aynı)
     * @param  string  $acceptedVia  'modal', 'checkbox', 'sms_otp', 'driver_registration', 'reservation'
     * @return LegalConsent|null  Aktif versiyon yoksa null
     */
    public function record(
        Request $request,
        string $consentType,
        ?string $textKey = null,
        string $acceptedVia = 'modal',
        ?array $extraPayload = null,
    ): ?LegalConsent {
        $key = $textKey ?? $consentType;
        $version = LegalTextVersion::currentFor($key);

        if (! $version) {
            // Aktif versiyon yok — büyük ihtimal seed eksik
            return null;
        }

        return LegalConsent::create([
            'user_id'             => Auth::id(),
            'session_id'          => $request->hasSession() ? $request->session()->getId() : null,
            'phone'               => $this->resolvePhone($request),
            'device_fingerprint'  => $request->input('fingerprint') ?: $request->header('X-Device-Fingerprint'),
            'text_version_id'     => $version->id,
            'text_key_snapshot'   => $version->key,
            'version_snapshot'    => $version->version,
            'sha256_snapshot'     => $version->sha256,
            'accepted_at'         => now(),
            'accepted_via'        => $acceptedVia,
            'consent_type'        => $consentType,
            'ip_address'          => $request->ip(),
            'user_agent'          => mb_substr((string) $request->userAgent(), 0, 1000),
            'locale'              => $request->getPreferredLanguage(['tr', 'en']),
            'request_url'         => mb_substr((string) $request->fullUrl(), 0, 500),
            'referer'             => mb_substr((string) $request->header('referer', ''), 0, 500),
            'raw_payload'         => $extraPayload,
        ]);
    }

    /**
     * Bir telefon OTP doğrulanınca, son N dakikadaki aynı session/ip'den
     * gelen anonim consent'lere bu telefonu yazar — dava halinde "bu telefon
     * bu metni gördü" zincirini kurar.
     */
    public function identifyByPhone(Request $request, string $phone, int $lookbackMinutes = 60): int
    {
        $normalized = preg_replace('/\D/', '', $phone);
        if (strlen($normalized) === 10 && ! str_starts_with($normalized, '90')) {
            $normalized = '90' . $normalized;
        }

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $ip = $request->ip();

        return DB::table('legal_consents')
            ->whereNull('phone')
            ->where(function ($q) use ($sessionId, $ip) {
                if ($sessionId) {
                    $q->where('session_id', $sessionId);
                }
                $q->orWhere('ip_address', $ip);
            })
            ->where('accepted_at', '>=', now()->subMinutes($lookbackMinutes))
            ->update(['phone' => $normalized, 'updated_at' => now()]);
    }

    /**
     * Bir kullanıcı login olduğunda anonim consent'lere user_id atar.
     */
    public function identifyByUserId(Request $request, int $userId, int $lookbackMinutes = 1440): int
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        if (! $sessionId) {
            return 0;
        }

        return DB::table('legal_consents')
            ->whereNull('user_id')
            ->where('session_id', $sessionId)
            ->where('accepted_at', '>=', now()->subMinutes($lookbackMinutes))
            ->update(['user_id' => $userId, 'updated_at' => now()]);
    }

    /**
     * Birden çok metin tek tetikte (form submit gibi) kabul edildiğinde
     * her biri için ayrı kayıt açar.
     *
     * @param  array<int,array{type:string,key?:string|null}>  $items
     * @return LegalConsent[]
     */
    public function recordMany(Request $request, array $items, string $acceptedVia, ?array $extraPayload = null): array
    {
        $created = [];
        foreach ($items as $item) {
            $consent = $this->record(
                request:      $request,
                consentType:  $item['type'],
                textKey:      $item['key'] ?? null,
                acceptedVia:  $acceptedVia,
                extraPayload: $extraPayload,
            );
            if ($consent) {
                $created[] = $consent;
            }
        }
        return $created;
    }

    protected function resolvePhone(Request $request): ?string
    {
        // Önce login olmuş kullanıcının telefonu
        $user = $request->user();
        if ($user && ! empty($user->phone)) {
            return $user->phone;
        }
        // Sonra payload'da gelen telefon
        $raw = (string) $request->input('phone', '');
        $digits = preg_replace('/\D/', '', $raw);
        if (! $digits) {
            return null;
        }
        if (strlen($digits) === 10 && ! str_starts_with($digits, '90')) {
            $digits = '90' . $digits;
        }
        return strlen($digits) >= 10 ? $digits : null;
    }
}
