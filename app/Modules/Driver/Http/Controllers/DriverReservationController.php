<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Services\ReservationDispatcherService;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Sürücü tarafı rezervasyon endpoint'leri:
 *   - GET  /surucu-paneli/api/reservations/market    — uygun rezervasyon pazarı listesi
 *   - GET  /surucu-paneli/api/reservations/mine      — sürücünün kabul ettikleri
 *   - POST /surucu-paneli/api/reservations/{publicId}/accept     — kabul
 *   - POST /surucu-paneli/api/reservations/{publicId}/confirm    — T-24h teyit
 *   - POST /surucu-paneli/api/reservations/{publicId}/cancel     — vazgeçme (pool'a geri)
 *
 * Privacy: müşteri telefonu/TC asla payload'a konmaz. Sadece ad + ⭐ + güven etiketi.
 */
class DriverReservationController extends Controller
{
    public function __construct(
        private ReservationDispatcherService $dispatcher,
    ) {}

    /** Sayfa: sürücüye Pazar + Aldıklarım sekmeli ekran */
    public function page(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) {
            return redirect()->route('driver.login');
        }
        return view('driver.reservations', compact('driver'));
    }

    /** Pazardaki uygun rezervasyonlar — sürücüye gösterilecek liste */
    public function market(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) {
            return response()->json(['ok' => false, 'message' => 'Yetkisiz.'], 401);
        }

        // Eligibility şartları: aktif paket + onaylı + askıda değil
        if (! $driver->hasActivePackage()) {
            return response()->json([
                'ok' => true,
                'reservations' => [],
                'message' => 'Aktif paketin yok. Pakete bak.',
            ]);
        }

        $vehicleClassId = $driver->currentVehicle?->vehicle_class_id;

        $query = Ride::query()
            ->where('status', Ride::STATUS_RES_POOL)
            ->where('city_id', $driver->city_id);

        if ($vehicleClassId) {
            $query->where('vehicle_class_id', $vehicleClassId);
        }

        $rides = $query->orderBy('scheduled_at')->limit(50)->get();

        // Sürücünün daha önce attığı rezervasyonları filtrele
        $rides = $rides->reject(function (Ride $r) use ($driver) {
            $rejected = $r->rejected_driver_ids ?? [];
            return in_array($driver->id, $rejected, true);
        });

        return response()->json([
            'ok' => true,
            'reservations' => $rides->values()->map(fn (Ride $r) => $this->marketPayload($r))->all(),
        ]);
    }

    /** Sürücünün kabul ettiği aktif rezervasyonları (geçmiş hariç) */
    public function mine(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) {
            return response()->json(['ok' => false, 'message' => 'Yetkisiz.'], 401);
        }

        $rides = Ride::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', Ride::RESERVATION_STATUSES)
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        return response()->json([
            'ok' => true,
            'reservations' => $rides->map(fn (Ride $r) => $this->minePayload($r))->all(),
        ]);
    }

    /** Sürücü pazardan kabul eder */
    public function accept(string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) {
            return response()->json(['ok' => false, 'message' => 'Yetkisiz.'], 401);
        }

        $ride = Ride::where('public_id', $publicId)->firstOrFail();

        try {
            $ride = $this->dispatcher->acceptByDriver($ride, $driver);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Rezervasyonu aldın. Müşteriye bildirim gitti.',
            'reservation' => $this->minePayload($ride),
        ]);
    }

    /** Sürücü T-24h reconfirm'e ✅ verir */
    public function confirm(string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) {
            return response()->json(['ok' => false, 'message' => 'Yetkisiz.'], 401);
        }

        $ride = Ride::where('public_id', $publicId)->firstOrFail();

        try {
            $ride = $this->dispatcher->confirmByDriver($ride, $driver);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Teyit verildi. Müşteriye bildirildi.',
            'reservation' => $this->minePayload($ride),
        ]);
    }

    /** Sürücü vazgeçer — rezervasyon pool'a geri döner, sürücüye puan düşer */
    public function cancel(Request $request, string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) {
            return response()->json(['ok' => false, 'message' => 'Yetkisiz.'], 401);
        }

        $ride = Ride::where('public_id', $publicId)->firstOrFail();

        try {
            $reason = (string) $request->input('reason', '');
            $this->dispatcher->cancelByDriver($ride, $driver, $reason ?: null);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Rezervasyondan çıktın.',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  PAYLOAD HELPERS — privacy: PII yok
    // ────────────────────────────────────────────────────────────────────

    protected function marketPayload(Ride $r): array
    {
        return [
            'public_id'         => $r->public_id,
            'pickup_address'    => $r->pickup_address,
            'dropoff_address'   => $r->dropoff_address,
            'distance_km'       => (float) $r->estimated_distance_km,
            'duration_minutes'  => (int) $r->estimated_duration_minutes,
            'scheduled_at'      => $r->scheduled_at?->toIso8601String(),
            'total_fare'        => (float) $r->total_fare,
            'currency'          => $r->currency,
            'passenger_count'   => (int) $r->passenger_count,
            'luggage_count'     => (int) $r->luggage_count,
            // PII yok: customer_name/phone/tc payload'da YOK
        ];
    }

    protected function minePayload(Ride $r): array
    {
        return array_merge($this->marketPayload($r), [
            'status'                  => $r->status,
            'accepted_at'             => $r->accepted_at?->toIso8601String(),
            'reconfirm_requested_at'  => $r->reconfirm_requested_at?->toIso8601String(),
            'reconfirm_deadline_at'   => $r->reconfirm_deadline_at?->toIso8601String(),
            'driver_reconfirmed_at'   => $r->driver_reconfirmed_at?->toIso8601String(),
            'imminent_notified_at'    => $r->imminent_notified_at?->toIso8601String(),
            'call_unlocked'           => $r->callUnlocked(),
            'chat_unlocked'           => $r->chatUnlocked(),
            // Sürücü kendi kabul ettiği için müşteri ADINI görür ama telefon yok
            'customer_name'           => $r->customer_name,
        ]);
    }

    protected function currentDriver(): ?Driver
    {
        $user = Auth::guard('driver')->user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }
}
