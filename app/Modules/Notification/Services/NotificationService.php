<?php

namespace App\Modules\Notification\Services;

use App\Models\User;
use App\Modules\Booking\Models\CustomerTrust;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Mobile\Models\DeviceToken;
use App\Modules\Notification\Models\NotificationCampaign;
use App\Modules\Notification\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Bildirim orkestratörü.
 *
 *  - İşlemsel bildirimler: yeni teklif (sürücüye), sürücü kabul etti / vardı (müşteriye),
 *    yeni mesaj (karşı tarafa).
 *  - Kampanya bildirimleri: admin panelden hedefli toplu gönderim.
 *
 * Her gönderim iki iş yapar: (1) inbox kaydı yazar (uygulama içi kutu),
 * (2) cihaz token'larına push atar (PushService). TÜM çağrılar best-effort —
 * bildirim hatası asıl akışı (yolculuk, ödeme) ASLA bozmaz.
 */
class NotificationService
{
    public function __construct(private PushService $push) {}

    // ─────────────────────────────────────────────────────────────
    //  İŞLEMSEL BİLDİRİMLER
    // ─────────────────────────────────────────────────────────────

    /** Yeni yolculuk teklifi → hedef sürücü(ler)e. */
    public function rideOfferToDrivers(array $driverIds, RideRequest $req): void
    {
        $this->safe(function () use ($driverIds, $req) {
            $userIds = $this->driverUserIds($driverIds);
            if (empty($userIds)) {
                return;
            }
            $fare = $req->currentPrice();
            $body = 'Alış: ' . $req->pickup_address
                . ($fare ? ' · ' . number_format((float) $fare, 0, ',', '.') . ' ₺' : '');

            $this->deliver($userIds, [
                'type'      => 'ride_offer',
                'title'     => 'Yeni yolculuk teklifi 🚕',
                'body'      => $body,
                'deep_link' => '/driver/offer/' . $req->public_id,
                'data'      => ['type' => 'ride_offer', 'public_id' => $req->public_id],
            ]);
        });
    }

    /** Sürücü teklifi kabul etti → müşteriye. */
    public function rideAcceptedToCustomer(RideRequest $req): void
    {
        $this->safe(function () use ($req) {
            $user = $this->customerUser($req->customer_phone);
            if (! $user) {
                return;
            }
            $this->deliver([$user->id], [
                'type'      => 'ride_accepted',
                'title'     => 'Sürücün yolda 🚗',
                'body'      => 'Üye sürücü teklifini kabul etti ve sana doğru geliyor.',
                'deep_link' => '/ride/' . $req->public_id,
                'data'      => ['type' => 'ride_accepted', 'public_id' => $req->public_id],
            ]);
        });
    }

    /** Sürücü buluşma noktasına vardı → müşteriye. */
    public function rideArrivedToCustomer(RideRequest $req): void
    {
        $this->safe(function () use ($req) {
            $user = $this->customerUser($req->customer_phone);
            if (! $user) {
                return;
            }
            $this->deliver([$user->id], [
                'type'      => 'ride_arrived',
                'title'     => 'Sürücün buluşma noktasında 📍',
                'body'      => 'Üye sürücü seni bekliyor. Lütfen buluşma noktasına gel.',
                'deep_link' => '/ride/' . $req->public_id,
                'data'      => ['type' => 'ride_arrived', 'public_id' => $req->public_id],
            ]);
        });
    }

    /**
     * Yeni sohbet mesajı → karşı tarafa.
     * $senderRole: 'customer' | 'driver' (gönderen). Bildirim diğer tarafa gider.
     */
    public function newMessage(RideRequest $req, string $senderRole, string $body): void
    {
        $this->safe(function () use ($req, $senderRole, $body) {
            $userIds = [];
            if ($senderRole === 'customer') {
                $userIds = $this->driverUserIds([$req->accepted_driver_id]);
            } elseif ($senderRole === 'driver') {
                $user = $this->customerUser($req->customer_phone);
                $userIds = $user ? [$user->id] : [];
            }
            if (empty($userIds)) {
                return;
            }
            $this->deliver($userIds, [
                'type'      => 'message',
                'title'     => $senderRole === 'driver' ? 'Sürücünden mesaj 💬' : 'Yolcudan mesaj 💬',
                'body'      => \Illuminate\Support\Str::limit($body, 120),
                'deep_link' => '/ride/' . $req->public_id,
                'data'      => ['type' => 'message', 'public_id' => $req->public_id],
            ]);
        });
    }

    /** Sürücü karşı teklif verdi → müşteriye. */
    public function driverCounterToCustomer(RideRequest $req, float $amount): void
    {
        $this->safe(function () use ($req, $amount) {
            $user = $this->customerUser($req->customer_phone);
            if (! $user) {
                return;
            }
            $this->deliver([$user->id], [
                'type'      => 'driver_counter',
                'title'     => 'Sürücüden karşı teklif 💰',
                'body'      => number_format($amount, 0, ',', '.') . ' ₺ karşı teklif geldi.',
                'deep_link' => '/ride/' . $req->public_id,
                'data'      => ['type' => 'driver_counter', 'public_id' => $req->public_id],
            ]);
        });
    }

    /** Yolcu karşı teklif verdi → (masadaki) sürücüye. */
    public function customerCounterToDriver(RideRequest $req, float $amount): void
    {
        $this->safe(function () use ($req, $amount) {
            $userIds = $this->driverUserIds([$req->offered_driver_id]);
            if (empty($userIds)) {
                return;
            }
            $this->deliver($userIds, [
                'type'      => 'customer_counter',
                'title'     => 'Yolcudan yeni teklif 💰',
                'body'      => number_format($amount, 0, ',', '.') . ' ₺ teklif geldi.',
                'deep_link' => '/driver/offer/' . $req->public_id,
                'data'      => ['type' => 'customer_counter', 'public_id' => $req->public_id],
            ]);
        });
    }

    /** Yolcu, sürücünün karşı teklifini kabul etti → sürücüye (anlaşma, yola çık). */
    public function agreementToDriver(RideRequest $req): void
    {
        $this->safe(function () use ($req) {
            $userIds = $this->driverUserIds([$req->accepted_driver_id ?: $req->offered_driver_id]);
            if (empty($userIds)) {
                return;
            }
            $this->deliver($userIds, [
                'type'      => 'ride_agreed',
                'title'     => 'Yolcu teklifini kabul etti ✅',
                'body'      => 'Anlaşma tamam — buluşma noktasına gidebilirsin.',
                'deep_link' => '/driver/ride/' . $req->public_id,
                'data'      => ['type' => 'ride_agreed', 'public_id' => $req->public_id],
            ]);
        });
    }

    /** Yolcu, kabul edilmiş yolculuğu iptal etti → (yoldaki) sürücüye. */
    public function rideCancelledToDriver(RideRequest $req): void
    {
        $this->safe(function () use ($req) {
            $userIds = $this->driverUserIds([$req->accepted_driver_id]);
            if (empty($userIds)) {
                return;
            }
            $this->deliver($userIds, [
                'type'      => 'ride_cancelled',
                'title'     => 'Yolcu yolculuğu iptal etti ❌',
                'body'      => 'Yolcu talebi iptal etti. Başka işlere dönebilirsin.',
                'deep_link' => '/driver',
                'data'      => ['type' => 'ride_cancelled', 'public_id' => $req->public_id],
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  KAMPANYA GÖNDERİMİ
    // ─────────────────────────────────────────────────────────────

    /**
     * Bir kampanyayı hedef kitlesine gönderir (inbox + push) ve istatistiği günceller.
     */
    public function dispatchCampaign(NotificationCampaign $campaign): NotificationCampaign
    {
        if (in_array($campaign->status, ['sending', 'sent'], true)) {
            return $campaign;
        }

        $campaign->update(['status' => 'sending']);

        $userIds = $this->resolveRecipientUserIds($campaign);

        $stats = ['sent' => 0, 'failed' => 0];
        if (! empty($userIds)) {
            $stats = $this->deliver($userIds, [
                'type'      => $campaign->type === 'promo' ? 'promo' : ($campaign->type === 'info' ? 'info' : 'announcement'),
                'title'     => $campaign->title,
                'body'      => $campaign->body,
                'image_url' => $campaign->image_url,
                'deep_link' => $campaign->deep_link,
                'data'      => array_filter([
                    'type'          => 'campaign',
                    'campaign_id'   => (string) $campaign->id,
                    'show_as_popup' => $campaign->show_as_popup ? '1' : '0',
                    'deep_link'     => $campaign->deep_link,
                ]),
            ], $campaign->id);
        }

        $campaign->update([
            'status'           => 'sent',
            'sent_at'          => now(),
            'recipients_count' => count($userIds),
            'sent_count'       => $stats['sent'],
            'failed_count'     => $stats['failed'],
        ]);

        return $campaign->fresh();
    }

    /** Admin önizlemesi: hedeflenen kullanıcı sayısı (göndermeden). */
    public function estimateRecipients(NotificationCampaign $campaign): int
    {
        return count($this->resolveRecipientUserIds($campaign));
    }

    /**
     * Kampanya hedefine uyan kullanıcı id'leri.
     *
     * @return int[]
     */
    public function resolveRecipientUserIds(NotificationCampaign $campaign): array
    {
        $target = is_array($campaign->target) ? $campaign->target : [];

        // Tekil kullanıcı seçimi → diğer filtreleri ezer
        if (! empty($target['user_ids']) && is_array($target['user_ids'])) {
            return User::query()
                ->whereIn('id', array_map('intval', $target['user_ids']))
                ->pluck('id')->all();
        }

        $query = User::query();

        // Rol
        $types = match ($campaign->audience) {
            'customers' => ['customer'],
            'drivers'   => ['driver'],
            default     => ['customer', 'driver'],
        };
        $query->whereIn('type', $types);

        // Tekil telefonlar
        if (! empty($target['phones']) && is_array($target['phones'])) {
            $digits = array_values(array_filter(array_map(
                fn ($p) => $this->last10((string) $p),
                $target['phones']
            )));
            if (! empty($digits)) {
                $query->where(function (Builder $q) use ($digits) {
                    foreach ($digits as $d) {
                        $q->orWhere('phone', 'like', '%' . $d);
                    }
                });
            }
        }

        // Sürücü filtreleri (city_id, women_only, active_package)
        $driverFilters = array_filter([
            'city_id'        => $target['city_id'] ?? null,
            'women_only'     => ! empty($target['women_only']),
            'active_package' => ! empty($target['active_package']),
        ], fn ($v) => $v !== null && $v !== false && $v !== '');

        if (! empty($driverFilters) && $campaign->audience !== 'customers') {
            $query->whereHas('driver', function (Builder $q) use ($target) {
                if (! empty($target['city_id'])) {
                    $q->where('city_id', (int) $target['city_id']);
                }
                if (! empty($target['women_only'])) {
                    $q->where('women_passengers_only', true);
                }
                if (! empty($target['active_package'])) {
                    $q->whereNotNull('package_active_until')->where('package_active_until', '>', now());
                }
            });
        }

        // Müşteri güven kademesi filtresi
        if (! empty($target['trust_tiers']) && is_array($target['trust_tiers']) && $campaign->audience !== 'drivers') {
            $phones = $this->phonesForTrustTiers($target['trust_tiers']);
            $query->where(function (Builder $q) use ($phones) {
                if (empty($phones)) {
                    $q->whereRaw('1 = 0'); // eşleşen yok
                    return;
                }
                foreach ($phones as $d) {
                    $q->orWhere('phone', 'like', '%' . $d);
                }
            });
        }

        return $query->pluck('id')->all();
    }

    // ─────────────────────────────────────────────────────────────
    //  ÇEKİRDEK GÖNDERİM
    // ─────────────────────────────────────────────────────────────

    /**
     * Inbox kaydı yaz + push at. Best-effort.
     *
     * @param  int[]  $userIds
     * @return array{sent:int, failed:int}
     */
    public function deliver(array $userIds, array $payload, ?int $campaignId = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $sent = 0; $failed = 0;
        if (empty($userIds)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $now = now();

        foreach (array_chunk($userIds, 500) as $chunk) {
            // 1) Inbox kayıtları (toplu insert)
            $rows = array_map(fn (int $uid) => [
                'user_id'                  => $uid,
                'type'                     => $payload['type'] ?? 'info',
                'title'                    => $payload['title'] ?? '',
                'body'                     => $payload['body'] ?? null,
                'image_url'                => $payload['image_url'] ?? null,
                'deep_link'                => $payload['deep_link'] ?? null,
                'data'                     => isset($payload['data']) ? json_encode($payload['data']) : null,
                'notification_campaign_id' => $campaignId,
                'created_at'               => $now,
                'updated_at'               => $now,
            ], $chunk);
            UserNotification::insert($rows);

            // 2) Push (token'ı olanlara)
            $tokens = DeviceToken::query()
                ->whereIn('user_id', $chunk)
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->all();

            if (! empty($tokens)) {
                $res = $this->push->sendToTokens(
                    $tokens,
                    $payload['title'] ?? '',
                    $payload['body'] ?? '',
                    is_array($payload['data'] ?? null) ? $payload['data'] : [],
                    $payload['image_url'] ?? null,
                );
                $sent += $res['sent'];
                $failed += $res['failed'];

                // Geçersiz token temizliği
                if (! empty($res['invalid_tokens'])) {
                    DeviceToken::whereIn('fcm_token', $res['invalid_tokens'])
                        ->update(['fcm_token' => null]);
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    // ─────────────────────────────────────────────────────────────
    //  YARDIMCILAR
    // ─────────────────────────────────────────────────────────────

    /** @param array<int|null> $driverIds  @return int[] user id'leri */
    private function driverUserIds(array $driverIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $driverIds)));
        if (empty($ids)) {
            return [];
        }
        return \App\Modules\Driver\Models\Driver::whereIn('id', $ids)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function customerUser(?string $phone): ?User
    {
        if (! $phone) {
            return null;
        }
        $d = $this->last10($phone);
        if (! $d) {
            return null;
        }
        return User::where('type', 'customer')
            ->where('phone', 'like', '%' . $d)
            ->first();
    }

    /** Telefonun son 10 hanesi (format bağımsız eşleştirme için). */
    private function last10(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 10) {
            return null;
        }
        return substr($digits, -10);
    }

    /** Verilen güven kademelerine düşen müşteri telefonları (son 10 hane). @return string[] */
    private function phonesForTrustTiers(array $tiers): array
    {
        $tiers = array_values(array_intersect($tiers, ['trusted', 'standard', 'new', 'suspicious']));
        if (empty($tiers)) {
            return [];
        }
        $out = [];
        CustomerTrust::query()->chunkById(500, function ($rows) use (&$out, $tiers) {
            foreach ($rows as $t) {
                if (in_array($t->boardingFeeTier(), $tiers, true)) {
                    $d = $this->last10((string) $t->phone);
                    if ($d) {
                        $out[] = $d;
                    }
                }
            }
        });
        return array_values(array_unique($out));
    }

    /** Bildirim işlerini asıl akıştan izole eder — hata olsa da yutar. */
    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('[NotificationService] bildirim gönderilemedi', ['err' => $e->getMessage()]);
        }
    }
}
