<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\Ride;
use App\Modules\Driver\Models\Driver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rezervasyon dispatcher — planlı yolculuk pazarı.
 *
 * Anlık dispatcher (DispatcherService) sürücüleri tek tek arar (30 sn TTL).
 * Rezervasyon dispatcher ise PAZAR modelidir: tüm uygun sürücüler aynı anda
 * görür, ilk kabul eden alır. Çünkü rezervasyon gelecekteki bir yolculuk —
 * şu anda online olmayan ama gelecekte müsait olacak sürücüler de aday.
 *
 * State machine:
 *   reservation_pending_pool
 *     ├─→ reservation_accepted               (sürücü kabul etti, >24h beklemede)
 *     │     └─→ reservation_reconfirm_requested  (T-24h)
 *     │           ├─→ reservation_confirmed     (sürücü teyit etti)
 *     │           └─→ reservation_pending_pool  (teyit fail → geri pool)
 *     ├─→ reservation_confirmed                  (kabul anında ≤24h ise direkt)
 *     │     └─→ reservation_imminent             (T-2h, maskeli arama açıldı)
 *     │           └─→ assigned (mevcut akışa devir → in_progress → completed)
 *     └─→ reservation_unmatched                  (12 saat kimse almadı)
 */
class ReservationDispatcherService
{
    /** Anlık akışa düşmesi için eşik — bundan kısa süreli rezervasyon "anlık" sayılır */
    public const INSTANT_THRESHOLD_HOURS = 2;

    /** Sürücü kabul anında ≤ bu kadarsa direkt confirmed sayılır (reconfirm yok) */
    public const SKIP_RECONFIRM_HOURS = 24;

    /** T-24h reconfirm penceresinin sınırı */
    public const RECONFIRM_LEAD_HOURS = 24;

    /** Sürücü teyit etmezse pool'a geri atma süresi (saat) — T-24h gönderildikten sonra */
    public const RECONFIRM_RESPONSE_TTL_HOURS = 12;

    /** T-2h imminent / maskeli arama açma penceresi */
    public const IMMINENT_LEAD_HOURS = 2;

    /** 12 saat kimse pool'dan almadıysa unmatched (müşteriye iade) */
    public const POOL_UNMATCHED_AFTER_HOURS = 12;

    /** Çakışma penceresi: kabul anında ± bu kadar saat içinde başka rezervasyon varsa engelle */
    public const CONFLICT_WINDOW_HOURS = 2;

    /**
     * Rezervasyonu pool'a yayınla.
     * ReservationService::create() bittikten sonra çağrılır.
     */
    public function publishToPool(Ride $ride): Ride
    {
        if (! $ride->scheduled_at) {
            throw new RuntimeException('Rezervasyon scheduled_at olmadan pool\'a yayılamaz.');
        }

        // ≤2h ise anlık akışa yönlendir, pool'a düşmesin
        if ($ride->scheduled_at->diffInMinutes(now(), false) > -(self::INSTANT_THRESHOLD_HOURS * 60)) {
            // diffInMinutes(now, false): scheduled - now negatif olur eğer scheduled gelecekteyse.
            // Burada amaç: scheduled - now < 2h ise instant'a yönlendir.
            $minutesUntil = now()->diffInMinutes($ride->scheduled_at, false);
            if ($minutesUntil < self::INSTANT_THRESHOLD_HOURS * 60) {
                Log::info('reservation.skip_pool.too_soon', [
                    'ride_id' => $ride->id,
                    'scheduled_at' => $ride->scheduled_at?->toIso8601String(),
                    'minutes_until' => $minutesUntil,
                ]);
                // Bu durumda standart pending'de bırakıyoruz; sonraki faz: anlık dispatcher'a forward
                return $ride;
            }
        }

        $ride->update([
            'status' => Ride::STATUS_RES_POOL,
            'pool_published_at' => now(),
        ]);

        $eligible = $this->findEligibleDrivers($ride);

        Log::info('reservation.pool_published', [
            'ride_id' => $ride->id,
            'public_id' => $ride->public_id,
            'scheduled_at' => $ride->scheduled_at?->toIso8601String(),
            'eligible_driver_count' => $eligible->count(),
        ]);

        $this->notifyDriversNewReservation($ride, $eligible);

        return $ride->fresh();
    }

    /**
     * Sürücü pool'dan rezervasyonu kabul eder.
     * Atomik: status kontrolü + çakışma kontrolü ile race condition'a karşı korumalı.
     *
     * @throws RuntimeException kabul edilemezse (başkası almış, çakışma, sürücü uygun değil)
     */
    public function acceptByDriver(Ride $ride, Driver $driver): Ride
    {
        return DB::transaction(function () use ($ride, $driver) {
            // Yeni snapshot al, kilitle
            $fresh = Ride::query()->whereKey($ride->id)->lockForUpdate()->first();
            if (! $fresh) {
                throw new RuntimeException('Rezervasyon bulunamadı.');
            }

            if ($fresh->status !== Ride::STATUS_RES_POOL) {
                throw new RuntimeException('Bu rezervasyon başka bir sürücü tarafından alındı.');
            }

            $this->assertDriverEligible($fresh, $driver);
            $this->assertNoScheduleConflict($fresh, $driver);

            $skipReconfirm = $fresh->scheduled_at
                && now()->diffInHours($fresh->scheduled_at, false) <= self::SKIP_RECONFIRM_HOURS;

            $updates = [
                'driver_id'   => $driver->id,
                'vehicle_id'  => $driver->current_vehicle_id,
                'accepted_at' => now(),
                'assigned_at' => now(),
            ];

            if ($skipReconfirm) {
                $updates['status'] = Ride::STATUS_RES_CONFIRMED;
                $updates['driver_reconfirmed_at'] = now();
            } else {
                $updates['status'] = Ride::STATUS_RES_ACCEPTED;
            }

            $fresh->update($updates);

            Log::info('reservation.accepted', [
                'ride_id' => $fresh->id,
                'driver_id' => $driver->id,
                'skip_reconfirm' => $skipReconfirm,
                'new_status' => $fresh->status,
            ]);

            $this->notifyCustomerDriverAssigned($fresh->fresh());

            return $fresh->fresh();
        });
    }

    /**
     * T-24h: sürücüden teyit iste.
     * Cron çağırır.
     */
    public function requestReconfirm(Ride $ride): Ride
    {
        if ($ride->status !== Ride::STATUS_RES_ACCEPTED) {
            return $ride;
        }

        $ride->update([
            'status' => Ride::STATUS_RES_RECONFIRM_REQ,
            'reconfirm_requested_at' => now(),
            'reconfirm_deadline_at' => now()->addHours(self::RECONFIRM_RESPONSE_TTL_HOURS),
        ]);

        Log::info('reservation.reconfirm_requested', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
            'deadline' => $ride->reconfirm_deadline_at?->toIso8601String(),
        ]);

        $this->notifyDriverReconfirmAsked($ride);
        $this->notifyCustomerReconfirmPending($ride);

        return $ride->fresh();
    }

    /** Sürücü ✅ — teyit etti */
    public function confirmByDriver(Ride $ride, Driver $driver): Ride
    {
        if ((int) $ride->driver_id !== (int) $driver->id) {
            throw new RuntimeException('Bu rezervasyon sana ait değil.');
        }
        if ($ride->status !== Ride::STATUS_RES_RECONFIRM_REQ) {
            throw new RuntimeException('Bu rezervasyon teyit aşamasında değil.');
        }

        $ride->update([
            'status' => Ride::STATUS_RES_CONFIRMED,
            'driver_reconfirmed_at' => now(),
        ]);

        Log::info('reservation.driver_confirmed', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
        ]);

        $this->notifyCustomerDriverConfirmed($ride->fresh());

        return $ride->fresh();
    }

    /** Sürücü ❌ ya da süre doldu — geri pool'a at, yeni sürücü ara */
    public function failReconfirm(Ride $ride, string $reason = 'no_response'): Ride
    {
        if (! in_array($ride->status, [Ride::STATUS_RES_RECONFIRM_REQ, Ride::STATUS_RES_ACCEPTED], true)) {
            return $ride;
        }

        $rejected = $ride->rejected_driver_ids ?? [];
        if ($ride->driver_id && ! in_array($ride->driver_id, $rejected, true)) {
            $rejected[] = $ride->driver_id;
        }

        $previousDriverId = $ride->driver_id;

        $ride->update([
            'status' => Ride::STATUS_RES_POOL,
            'driver_id' => null,
            'vehicle_id' => null,
            'accepted_at' => null,
            'assigned_at' => null,
            'reconfirm_requested_at' => null,
            'reconfirm_deadline_at' => null,
            'driver_reconfirmed_at' => null,
            'rejected_driver_ids' => $rejected,
            'pool_published_at' => now(),
        ]);

        Log::warning('reservation.reconfirm_failed', [
            'ride_id' => $ride->id,
            'previous_driver_id' => $previousDriverId,
            'reason' => $reason,
        ]);

        $eligible = $this->findEligibleDrivers($ride->fresh())
            ->reject(fn (Driver $d) => in_array($d->id, $rejected, true));

        $this->notifyDriversNewReservation($ride->fresh(), $eligible);
        $this->notifyCustomerDriverDropped($ride->fresh());

        return $ride->fresh();
    }

    /** T-2h: hatırlatma + maskeli arama açma */
    public function markImminent(Ride $ride): Ride
    {
        if ($ride->status !== Ride::STATUS_RES_CONFIRMED) {
            return $ride;
        }

        $ride->update([
            'status' => Ride::STATUS_RES_IMMINENT,
            'imminent_notified_at' => now(),
            'masked_call_unlocked_at' => now(),
        ]);

        Log::info('reservation.imminent', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
        ]);

        $this->notifyImminentReminder($ride->fresh());

        return $ride->fresh();
    }

    /** 12 saat geçti, kimse almadı → unmatched */
    public function markUnmatched(Ride $ride): Ride
    {
        if ($ride->status !== Ride::STATUS_RES_POOL) {
            return $ride;
        }

        $ride->update([
            'status' => Ride::STATUS_RES_UNMATCHED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'pool_unmatched',
        ]);

        Log::warning('reservation.unmatched', [
            'ride_id' => $ride->id,
            'scheduled_at' => $ride->scheduled_at?->toIso8601String(),
        ]);

        $this->notifyCustomerUnmatched($ride->fresh());

        return $ride->fresh();
    }

    /** Müşteri iptal eder */
    public function cancelByCustomer(Ride $ride, ?string $reason = null): Ride
    {
        if (! in_array($ride->status, [
            Ride::STATUS_RES_POOL,
            Ride::STATUS_RES_ACCEPTED,
            Ride::STATUS_RES_RECONFIRM_REQ,
            Ride::STATUS_RES_CONFIRMED,
            Ride::STATUS_RES_IMMINENT,
        ], true)) {
            throw new RuntimeException('Bu durumda rezervasyon iptal edilemez.');
        }

        $ride->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $ride->customer_user_id,
            'cancellation_reason' => $reason ?: 'customer_request',
        ]);

        Log::info('reservation.cancelled_by_customer', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
        ]);

        if ($ride->driver_id) {
            $this->notifyDriverCancelled($ride->fresh());
        }

        return $ride->fresh();
    }

    /** Sürücü iptal eder — geri pool'a */
    public function cancelByDriver(Ride $ride, Driver $driver, ?string $reason = null): Ride
    {
        if ((int) $ride->driver_id !== (int) $driver->id) {
            throw new RuntimeException('Bu rezervasyon sana ait değil.');
        }

        return $this->failReconfirm($ride, $reason ?: 'driver_cancelled');
    }

    // ────────────────────────────────────────────────────────────────────
    //  ELIGIBILITY + CONFLICT
    // ────────────────────────────────────────────────────────────────────

    /**
     * Şehir + araç sınıfı + aktif paket eşleşen sürücüler.
     * Pool yayında: online olmayanlar da aday — push ile çağrılıyor.
     */
    public function findEligibleDrivers(Ride $ride): Collection
    {
        $rejected = $ride->rejected_driver_ids ?? [];

        // Tek-kademe (Martı TAG) model: araç sınıfı sınıf-bağımsız — sadece aracı olan
        // uygun sürücüler süzülür, sınıf eşleşmesi aranmaz.
        $query = Driver::query()
            ->where('approval_status', 'approved')
            ->where('is_suspended', false)
            ->where('city_id', $ride->city_id)
            ->whereNotNull('package_active_until')
            ->where('package_active_until', '>', now())
            ->whereHas('currentVehicle');

        if (! empty($rejected)) {
            $query->whereNotIn('id', $rejected);
        }

        return $query->get();
    }

    protected function assertDriverEligible(Ride $ride, Driver $driver): void
    {
        if ($driver->approval_status !== 'approved') {
            throw new RuntimeException('Hesabın onaylanmamış.');
        }
        if ($driver->is_suspended) {
            throw new RuntimeException('Hesabın askıda.');
        }
        if (! $driver->hasActivePackage()) {
            throw new RuntimeException('Aktif paketin yok. Paket satın al.');
        }
        if ((int) $driver->city_id !== (int) $ride->city_id) {
            throw new RuntimeException('Bu şehirde değilsin.');
        }
        // Tek-kademe (Martı TAG) model: araç sınıfı eşleşmesi aranmaz (no-op).
        // Yalnızca sürücünün aktif bir aracı olması yeterli.
        $vehicle = $driver->currentVehicle;
        if (! $vehicle) {
            throw new RuntimeException('Aracın tanımlı değil.');
        }
    }

    /**
     * Aynı sürücünün scheduled_at ± CONFLICT_WINDOW_HOURS içinde başka kabul edilmiş
     * rezervasyonu var mı? Varsa kabul engellenir.
     */
    protected function assertNoScheduleConflict(Ride $ride, Driver $driver): void
    {
        if (! $ride->scheduled_at) return;

        $window = self::CONFLICT_WINDOW_HOURS;
        $start = (clone $ride->scheduled_at)->subHours($window);
        $end   = (clone $ride->scheduled_at)->addHours($window);

        $conflict = Ride::query()
            ->where('driver_id', $driver->id)
            ->where('id', '!=', $ride->id)
            ->whereIn('status', [
                Ride::STATUS_RES_ACCEPTED,
                Ride::STATUS_RES_RECONFIRM_REQ,
                Ride::STATUS_RES_CONFIRMED,
                Ride::STATUS_RES_IMMINENT,
                'assigned', 'driver_arriving', 'in_progress',
            ])
            ->whereBetween('scheduled_at', [$start, $end])
            ->exists();

        if ($conflict) {
            throw new RuntimeException(
                'Bu saatte çakışan başka bir rezervasyonun var. ±' . $window . ' saat boşluk gerekli.'
            );
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  CRON TICK HELPERS
    // ────────────────────────────────────────────────────────────────────

    /** T-24h penceresine giren accepted rezervasyonları reconfirm akışına sok */
    public function tickReconfirm(): int
    {
        $threshold = now()->addHours(self::RECONFIRM_LEAD_HOURS);

        $rides = Ride::query()
            ->where('status', Ride::STATUS_RES_ACCEPTED)
            ->where('scheduled_at', '<=', $threshold)
            ->where('scheduled_at', '>', now())
            ->limit(50)
            ->get();

        $n = 0;
        foreach ($rides as $r) {
            $this->requestReconfirm($r);
            $n++;
        }
        return $n;
    }

    /** Reconfirm deadline geçenleri geri pool'a at */
    public function tickReconfirmTimeout(): int
    {
        $rides = Ride::query()
            ->where('status', Ride::STATUS_RES_RECONFIRM_REQ)
            ->whereNotNull('reconfirm_deadline_at')
            ->where('reconfirm_deadline_at', '<=', now())
            ->limit(50)
            ->get();

        $n = 0;
        foreach ($rides as $r) {
            $this->failReconfirm($r, 'reconfirm_timeout');
            $n++;
        }
        return $n;
    }

    /** T-2h penceresine giren confirmed rezervasyonları imminent yap */
    public function tickImminent(): int
    {
        $threshold = now()->addHours(self::IMMINENT_LEAD_HOURS);

        $rides = Ride::query()
            ->where('status', Ride::STATUS_RES_CONFIRMED)
            ->where('scheduled_at', '<=', $threshold)
            ->where('scheduled_at', '>', now())
            ->limit(50)
            ->get();

        $n = 0;
        foreach ($rides as $r) {
            $this->markImminent($r);
            $n++;
        }
        return $n;
    }

    /** 12 saatten uzun süredir pool'da bekleyen rezervasyonları unmatched yap */
    public function tickUnmatched(): int
    {
        $threshold = now()->subHours(self::POOL_UNMATCHED_AFTER_HOURS);

        $rides = Ride::query()
            ->where('status', Ride::STATUS_RES_POOL)
            ->whereNotNull('pool_published_at')
            ->where('pool_published_at', '<=', $threshold)
            ->limit(50)
            ->get();

        $n = 0;
        foreach ($rides as $r) {
            $this->markUnmatched($r);
            $n++;
        }
        return $n;
    }

    // ────────────────────────────────────────────────────────────────────
    //  NOTIFICATIONS  — inbox + FCM push (NotificationService), best-effort.
    //  Privacy: müşteri/sürücü ham PII (telefon, plaka) push body'sine konmaz.
    // ────────────────────────────────────────────────────────────────────

    protected function notifyDriversNewReservation(Ride $ride, Collection $drivers): void
    {
        Log::info('notify.drivers.new_reservation', [
            'ride_id' => $ride->id,
            'driver_ids' => $drivers->pluck('id')->all(),
            'scheduled_at' => $ride->scheduled_at?->toIso8601String(),
            'pickup' => $ride->pickup_address,
            'dropoff' => $ride->dropoff_address,
            'fare' => (float) $ride->total_fare,
        ]);

        $when = $ride->scheduled_at?->format('d.m H:i');
        $fare = $ride->total_fare ? number_format((float) $ride->total_fare, 0, ',', '.') . ' ₺' : null;
        $body = trim(($when ? $when . ' · ' : '') . (string) $ride->pickup_address . ($fare ? ' · ' . $fare : ''));

        $this->pushReservation($this->driverUserIds($drivers->pluck('id')->all()), $ride, [
            'type'      => 'reservation_offer',
            'title'     => 'Yeni rezervasyon 📅',
            'body'      => $body,
            'deep_link' => '/driver/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyCustomerDriverAssigned(Ride $ride): void
    {
        Log::info('notify.customer.driver_assigned', [
            'ride_id' => $ride->id,
            'customer_user_id' => $ride->customer_user_id,
            'driver_id' => $ride->driver_id,
            'status' => $ride->status,
        ]);

        $this->pushReservation([(int) $ride->customer_user_id], $ride, [
            'type'      => 'reservation_assigned',
            'title'     => 'Rezervasyonuna sürücü atandı 🚗',
            'body'      => 'Planlı yolculuğun için bir üye sürücü atandı.',
            'deep_link' => '/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyDriverReconfirmAsked(Ride $ride): void
    {
        Log::info('notify.driver.reconfirm_asked', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
            'deadline' => $ride->reconfirm_deadline_at?->toIso8601String(),
        ]);

        $when = $ride->scheduled_at?->format('d.m H:i');
        $this->pushReservation($this->driverUserIds([$ride->driver_id]), $ride, [
            'type'      => 'reservation_reconfirm',
            'title'     => 'Rezervasyonu onayla ⏳',
            'body'      => ($when ? $when . ' · ' : '') . 'Yaklaşan rezervasyonu teyit etmen gerekiyor.',
            'deep_link' => '/driver/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyCustomerReconfirmPending(Ride $ride): void
    {
        Log::info('notify.customer.reconfirm_pending', [
            'ride_id' => $ride->id,
            'customer_user_id' => $ride->customer_user_id,
        ]);

        $this->pushReservation([(int) $ride->customer_user_id], $ride, [
            'type'      => 'reservation_reconfirm_pending',
            'title'     => 'Sürücü onayı bekleniyor ⏳',
            'body'      => 'Sürücünün rezervasyonunu teyit etmesi bekleniyor.',
            'deep_link' => '/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyCustomerDriverConfirmed(Ride $ride): void
    {
        Log::info('notify.customer.driver_confirmed', [
            'ride_id' => $ride->id,
            'customer_user_id' => $ride->customer_user_id,
            'driver_id' => $ride->driver_id,
        ]);

        $when = $ride->scheduled_at?->format('d.m H:i');
        $this->pushReservation([(int) $ride->customer_user_id], $ride, [
            'type'      => 'reservation_confirmed',
            'title'     => 'Sürücün rezervasyonu onayladı ✅',
            'body'      => ($when ? $when . ' · ' : '') . 'Sürücün planlı yolculuğunu teyit etti.',
            'deep_link' => '/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyCustomerDriverDropped(Ride $ride): void
    {
        Log::info('notify.customer.driver_dropped', [
            'ride_id' => $ride->id,
            'customer_user_id' => $ride->customer_user_id,
        ]);

        $this->pushReservation([(int) $ride->customer_user_id], $ride, [
            'type'      => 'reservation_driver_dropped',
            'title'     => 'Yeni sürücü aranıyor 🔄',
            'body'      => 'Atanan sürücü ayrıldı; rezervasyonun için yeni sürücü aranıyor.',
            'deep_link' => '/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyImminentReminder(Ride $ride): void
    {
        Log::info('notify.imminent', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
            'customer_user_id' => $ride->customer_user_id,
            'scheduled_at' => $ride->scheduled_at?->toIso8601String(),
        ]);

        $when = $ride->scheduled_at?->format('d.m H:i');
        $body = trim(($when ? $when . ' · ' : '') . (string) $ride->pickup_address);

        // Yaklaşan yolculuk → hem müşteriye hem sürücüye
        $this->pushReservation([(int) $ride->customer_user_id], $ride, [
            'type'      => 'reservation_imminent',
            'title'     => 'Yolculuğun yaklaşıyor ⏰',
            'body'      => $body,
            'deep_link' => '/reservation/' . $ride->public_id,
        ]);
        $this->pushReservation($this->driverUserIds([$ride->driver_id]), $ride, [
            'type'      => 'reservation_imminent',
            'title'     => 'Rezervasyon yolculuğu yaklaşıyor ⏰',
            'body'      => $body,
            'deep_link' => '/driver/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyCustomerUnmatched(Ride $ride): void
    {
        Log::info('notify.customer.unmatched', [
            'ride_id' => $ride->id,
            'customer_user_id' => $ride->customer_user_id,
        ]);

        $this->pushReservation([(int) $ride->customer_user_id], $ride, [
            'type'      => 'reservation_unmatched',
            'title'     => 'Rezervasyona sürücü bulunamadı 😔',
            'body'      => 'Planlı yolculuğun için uygun sürücü bulunamadı. Lütfen tekrar dene.',
            'deep_link' => '/reservation/' . $ride->public_id,
        ]);
    }

    protected function notifyDriverCancelled(Ride $ride): void
    {
        Log::info('notify.driver.cancelled_by_customer', [
            'ride_id' => $ride->id,
            'driver_id' => $ride->driver_id,
        ]);

        $this->pushReservation($this->driverUserIds([$ride->driver_id]), $ride, [
            'type'      => 'reservation_cancelled',
            'title'     => 'Rezervasyon iptal edildi',
            'body'      => 'Müşteri planlı yolculuğu iptal etti.',
            'deep_link' => '/driver/reservation/' . $ride->public_id,
        ]);
    }

    /**
     * Rezervasyon bildirimi gönder — inbox + FCM push (best-effort).
     * NotificationService::deliver kullanır; bildirim hatası dispatch akışını
     * ASLA bozmaz. data payload'ı app'in deep-link kurması için type+public_id taşır.
     *
     * @param  int[]  $userIds
     * @param  array<string,mixed>  $payload  type,title,body,deep_link
     */
    private function pushReservation(array $userIds, Ride $ride, array $payload): void
    {
        try {
            $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
            if (empty($userIds)) {
                return;
            }
            $payload['data'] = [
                'type'      => $payload['type'] ?? 'reservation',
                'public_id' => (string) $ride->public_id,
            ];
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->deliver($userIds, $payload);
        } catch (\Throwable $e) {
            Log::warning('[ReservationDispatcher] push başarısız', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Sürücü id'lerini bildirim için user id'lerine çevirir.
     *
     * @param  array<int|null>  $driverIds
     * @return int[]
     */
    private function driverUserIds(array $driverIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $driverIds)));
        if (empty($ids)) {
            return [];
        }
        return Driver::whereIn('id', $ids)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
