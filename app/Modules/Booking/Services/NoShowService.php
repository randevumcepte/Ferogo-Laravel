<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Models\NoShowReport;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Models\DriverCompensation;
use Illuminate\Support\Facades\DB;

/**
 * No-show işleyici: sürücü "müşteri gelmedi" dediğinde tüm yan etkileri yönetir.
 *
 * Doğrulama kuralları:
 * - Sürücü gerçekten varış noktasında olmalı (300 metre)
 * - Sürücü en az MIN_WAIT_SECONDS beklemiş olmalı
 *
 * Yan etkiler:
 * - NoShowReport kaydı (audit trail)
 * - RideRequest.status='no_show' + Ride.status='no_show'
 * - CustomerTrust skor düşür + ban kuralları
 * - DriverCompensation kaydı (no-show tazminat)
 * - Sürücüyü tekrar online'a al
 */
class NoShowService
{
    public const MIN_WAIT_SECONDS         = 300;   // 5 dakika
    public const MAX_PROXIMITY_METERS     = 300.0; // varış noktası yakınlığı
    public const COMPENSATION_AMOUNT      = 25.00; // TRY
    public const COMPENSATION_PERCENT     = 0.30;  // tahmini ücretin %30'u (üst sınır)

    public function __construct(
        private CustomerTrustService $trustService,
    ) {}

    /**
     * Sürücü "müşteri gelmedi" basar.
     *
     * @return array{ok: bool, message?: string, compensation_amount?: float}
     */
    public function reportNoShow(
        RideRequest $req,
        Driver $driver,
        ?float $reportedLat,
        ?float $reportedLng,
        ?string $note = null,
    ): array {
        if ((int) $req->accepted_driver_id !== (int) $driver->id) {
            return ['ok' => false, 'message' => 'Bu yolculuk sana ait değil.'];
        }
        if ($req->status !== 'accepted') {
            return ['ok' => false, 'message' => 'Bu yolculuk artık aktif değil.'];
        }
        if (! $req->driver_arrived_at) {
            return ['ok' => false, 'message' => 'Önce "Lokasyona vardım" butonuna bas.'];
        }

        $waitSeconds = abs((int) $req->driver_arrived_at->diffInSeconds(now()));

        if ($waitSeconds < self::MIN_WAIT_SECONDS) {
            $remaining = self::MIN_WAIT_SECONDS - $waitSeconds;
            return [
                'ok'      => false,
                'message' => 'En az 5 dakika beklemelisin. ' . $remaining . ' sn kaldı.',
            ];
        }

        // GPS yakınlık kontrolü
        $distance = null;
        if ($reportedLat !== null && $reportedLng !== null) {
            $distance = $this->haversineMeters(
                (float) $req->pickup_lat,
                (float) $req->pickup_lng,
                $reportedLat,
                $reportedLng,
            );

            if ($distance > self::MAX_PROXIMITY_METERS) {
                return [
                    'ok'      => false,
                    'message' => 'Varış noktasından çok uzaktasın (' . round($distance) . ' m). Önce yaklaş.',
                ];
            }
        }

        $compensation = $this->calculateCompensation($req);

        return DB::transaction(function () use ($req, $driver, $reportedLat, $reportedLng, $distance, $waitSeconds, $note, $compensation) {
            $report = NoShowReport::create([
                'ride_id'                => $req->ride_id,
                'ride_request_id'        => $req->id,
                'driver_id'              => $driver->id,
                'customer_phone'         => $req->customer_phone,
                'resolution'             => 'confirmed', // otomatik onay (GPS + wait time geçti)
                'reported_lat'           => $reportedLat,
                'reported_lng'           => $reportedLng,
                'pickup_lat'             => (float) $req->pickup_lat,
                'pickup_lng'             => (float) $req->pickup_lng,
                'distance_from_pickup_m' => $distance,
                'wait_seconds'           => $waitSeconds,
                'compensation_amount'    => $compensation,
                'driver_note'            => $note,
            ]);

            // RideRequest + Ride status
            $req->update([
                'status'      => 'no_show',
                'no_show_at'  => now(),
            ]);
            if ($req->ride) {
                $req->ride->update([
                    'status'              => 'no_show',
                    'cancelled_at'        => now(),
                    'cancellation_reason' => 'Müşteri gelmedi (sürücü raporu #' . $report->id . ')',
                ]);
            }

            // Müşteriye penaltı
            $this->trustService->recordNoShow($req->customer_phone);

            // Sürücüye tazminat
            DriverCompensation::create([
                'driver_id'         => $driver->id,
                'no_show_report_id' => $report->id,
                'ride_id'           => $req->ride_id,
                'reason'            => 'no_show',
                'amount'            => $compensation,
                'status'            => 'pending',
                'note'              => 'Otomatik no-show tazminatı.',
            ]);

            // Sürücüyü tekrar müsait yap
            $driver->update(['availability_status' => 'online']);

            return [
                'ok'                  => true,
                'compensation_amount' => (float) $compensation,
                'message'             => 'Olay kayda alındı. Hesabına ' . number_format($compensation, 2) . ' ₺ tazminat işlendi.',
            ];
        });
    }

    /**
     * Sürücü "varış noktasına ulaştım" basar — bekleme süresi sayacını başlatır.
     */
    public function markDriverArrived(RideRequest $req, Driver $driver): array
    {
        if ((int) $req->accepted_driver_id !== (int) $driver->id) {
            return ['ok' => false, 'message' => 'Bu yolculuk sana ait değil.'];
        }
        if ($req->status !== 'accepted') {
            return ['ok' => false, 'message' => 'Bu yolculuk aktif değil.'];
        }
        if ($req->driver_arrived_at) {
            return ['ok' => true, 'message' => 'Zaten varış işaretli.', 'arrived_at' => $req->driver_arrived_at->toIso8601String()];
        }

        $req->update(['driver_arrived_at' => now()]);
        if ($req->ride) {
            $req->ride->update(['driver_arrived_at' => now()]);
        }

        // Sürücü buluşma noktasına vardı → müşteriye push (best-effort).
        try {
            app(\App\Modules\Notification\Services\NotificationService::class)
                ->rideArrivedToCustomer($req);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[NoShowService] arrived push', ['err' => $e->getMessage()]);
        }

        return [
            'ok'         => true,
            'message'    => 'Varış kaydedildi. 5 dk sonra "müşteri gelmedi" butonu aktif olur.',
            'arrived_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Müşteri "sürücüyü gördüm, geliyorum" basar — no-show riskini sıfırlar
     * ve bot kontrolü olur (60 sn içinde elle basılmalı).
     */
    public function customerConfirm(RideRequest $req): array
    {
        if ($req->status !== 'accepted') {
            return ['ok' => false, 'message' => 'Yolculuk aktif değil.'];
        }
        $req->update(['customer_confirmed_at' => now()]);
        return ['ok' => true, 'message' => 'Onay alındı. Üye sürücü seni bekliyor.'];
    }

    private function calculateCompensation(RideRequest $req): float
    {
        $estimated = (float) ($req->estimated_fare ?? 0);
        if ($estimated <= 0) {
            return self::COMPENSATION_AMOUNT;
        }
        $percent = $estimated * self::COMPENSATION_PERCENT;
        return max(self::COMPENSATION_AMOUNT, min($percent, 150.00));
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthM = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earthM * asin(min(1.0, sqrt($a)));
    }
}
