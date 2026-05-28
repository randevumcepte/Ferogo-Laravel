<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\CustomerTrust;
use Illuminate\Support\Facades\DB;

/**
 * Müşteri güven sistemi — fake çağrı / no-show / rakip sabotajına karşı koruma.
 *
 * Skor 0-100. Başlangıç 50.
 * Pozitif olaylar: tamamlanan yolculuk (+10), düzenli kullanım (zamanla +)
 * Negatif olaylar: no-show (-30), geç iptal (-10), spam talep (-5)
 *
 * Ban kuralları:
 * - 24 saatte 2 no-show → 1 saat cooldown
 * - 24 saatte 3 no-show → 24 saat cooldown
 * - Toplam 5 no-show → kalıcı kara liste
 * - Trust score < 10 → kalıcı kara liste
 */
class CustomerTrustService
{
    public const SCORE_INITIAL          = 50;
    public const SCORE_RIDE_COMPLETED   = 10;
    public const SCORE_NO_SHOW_PENALTY  = -30;
    public const SCORE_CANCEL_PENALTY   = -5;
    public const SCORE_LATE_CANCEL      = -10;

    public const BLACKLIST_NO_SHOW_THRESHOLD = 5;
    public const BLACKLIST_SCORE_THRESHOLD   = 10;

    /**
     * Bu telefon yeni bir ride request yaratabilir mi?
     *
     * @return array{ok: bool, reason?: string, retry_after?: int, label: string}
     */
    public function canRequestRide(string $phone, ?string $ip = null, ?string $fingerprint = null): array
    {
        $trust = $this->getOrCreate($phone);

        if ($trust->is_blacklisted) {
            return [
                'ok'     => false,
                'reason' => 'Hesabın güvenlik nedeniyle askıya alındı. Destek ile iletişime geç.',
                'label'  => 'blacklisted',
            ];
        }

        if ($trust->banned_until && $trust->banned_until->isFuture()) {
            $retryAfter = max(60, (int) now()->diffInSeconds($trust->banned_until, false));
            $human = $trust->banned_until->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE);
            return [
                'ok'          => false,
                'reason'      => "Çok sayıda çağrıya cevap vermedin. {$human} sonra tekrar dene.",
                'retry_after' => $retryAfter,
                'label'       => 'cooldown',
            ];
        }

        // Trust score çok düşükse → engelle
        if ($trust->trust_score < self::BLACKLIST_SCORE_THRESHOLD) {
            $trust->update([
                'is_blacklisted'   => true,
                'blacklisted_at'   => now(),
                'blacklist_reason' => 'Güven skoru sıfıra düştü.',
            ]);
            return [
                'ok'     => false,
                'reason' => 'Hesabın güvenlik nedeniyle askıya alındı.',
                'label'  => 'blacklisted',
            ];
        }

        return ['ok' => true, 'label' => $trust->trustLabel()];
    }

    public function recordRequestCreated(string $phone, ?string $ip = null, ?string $fingerprint = null): CustomerTrust
    {
        $trust = $this->getOrCreate($phone);
        $trust->fill([
            'last_request_at'  => now(),
            'last_ip'          => $ip ?? $trust->last_ip,
            'last_fingerprint' => $fingerprint ?? $trust->last_fingerprint,
        ]);
        $trust->increment('total_requests');
        return $trust->fresh();
    }

    public function recordRideCompleted(string $phone): CustomerTrust
    {
        $trust = $this->getOrCreate($phone);

        return DB::transaction(function () use ($trust) {
            $trust->total_completed += 1;
            $trust->last_completed_at = now();
            $trust->trust_score = min(100, $trust->trust_score + self::SCORE_RIDE_COMPLETED);
            $trust->save();
            return $trust->fresh();
        });
    }

    public function recordCustomerCancellation(string $phone, bool $late = false): CustomerTrust
    {
        $trust = $this->getOrCreate($phone);

        return DB::transaction(function () use ($trust, $late) {
            $trust->total_customer_cancellations += 1;
            $delta = $late ? self::SCORE_LATE_CANCEL : self::SCORE_CANCEL_PENALTY;
            $trust->trust_score = max(0, $trust->trust_score + $delta);
            $trust->save();
            return $trust->fresh();
        });
    }

    /**
     * No-show olayı — en kritik penaltı.
     * Cooldown ve kara liste kararları burada verilir.
     */
    public function recordNoShow(string $phone): CustomerTrust
    {
        $trust = $this->getOrCreate($phone);

        return DB::transaction(function () use ($trust) {
            $trust->total_no_shows += 1;
            $trust->last_no_show_at = now();
            $trust->trust_score = max(0, $trust->trust_score + self::SCORE_NO_SHOW_PENALTY);

            // 24 saatlik pencere
            $windowStart = $trust->no_shows_24h_window_start;
            if (! $windowStart || $windowStart->lt(now()->subDay())) {
                $trust->no_shows_24h = 1;
                $trust->no_shows_24h_window_start = now();
            } else {
                $trust->no_shows_24h += 1;
            }

            // Cooldown kararları
            if ($trust->no_shows_24h >= 3) {
                $trust->banned_until = now()->addDay();
                $trust->ban_reason = '24 saatte 3+ no-show.';
            } elseif ($trust->no_shows_24h >= 2) {
                $trust->banned_until = now()->addHour();
                $trust->ban_reason = '24 saatte 2 no-show.';
            }

            // Kalıcı kara liste
            if ($trust->total_no_shows >= self::BLACKLIST_NO_SHOW_THRESHOLD
                || $trust->trust_score <= self::BLACKLIST_SCORE_THRESHOLD) {
                $trust->is_blacklisted = true;
                $trust->blacklisted_at = now();
                $trust->blacklist_reason = 'Sınır aşıldı (' . $trust->total_no_shows . ' no-show).';
            }

            $trust->save();
            return $trust->fresh();
        });
    }

    public function getOrCreate(string $phone): CustomerTrust
    {
        return CustomerTrust::firstOrCreate(
            ['phone' => $this->normalizePhone($phone)],
            ['trust_score' => self::SCORE_INITIAL]
        );
    }

    /**
     * Telefon normalizasyonu: tek bir kişi farklı format girse de aynı satıra düşsün.
     * "0532 123 45 67", "+90 532 123 45 67", "5321234567" → 5321234567
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        // TR ülke kodu temizliği
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }
        return $digits;
    }
}
