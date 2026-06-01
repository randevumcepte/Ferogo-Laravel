<?php

namespace App\Modules\Payment\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Models\DriverPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverPackageService
{
    /**
     * Sürücü için yeni paket satın alma kaydı oluşturur (pending durumunda).
     * Ödeme tamamlanınca markPaidAndActivate() çağrılır.
     */
    public function createPurchase(Driver $driver, string $type): DriverPackage
    {
        $def = config("packages.types.{$type}");
        if (! $def) {
            throw ValidationException::withMessages(['type' => 'Geçersiz paket tipi.']);
        }

        $gateway = GatewayFactory::make();

        return DriverPackage::create([
            'driver_id'        => $driver->id,
            'type'             => $type,
            'duration_hours'   => (int) $def['duration_hours'],
            'price'            => (float) $def['price'],
            'status'           => 'pending',
            'payment_provider' => $gateway->name(),
        ]);
    }

    /**
     * Ödeme onaylandı → paketi aktive et + sürücünün cache alanını güncelle.
     *
     * Önemli mantık: Sürücünün hâlâ aktif başka paketi varsa,
     * yeni paketin başlangıç zamanı eski paketin bitişine eklenir
     * (sürücü ödediği zamanı kaybetmesin).
     */
    public function markPaidAndActivate(DriverPackage $package, string $reference, array $rawMeta = []): DriverPackage
    {
        return DB::transaction(function () use ($package, $reference, $rawMeta) {
            $driver = $package->driver()->lockForUpdate()->first();
            if (! $driver) {
                throw new \RuntimeException('Sürücü bulunamadı.');
            }

            $now = now();
            $startsAt = ($driver->package_active_until && $driver->package_active_until->isFuture())
                ? $driver->package_active_until
                : $now;

            $expiresAt = $startsAt->copy()->addHours($package->duration_hours);

            $package->update([
                'status'             => 'active',
                'starts_at'          => $startsAt,
                'expires_at'         => $expiresAt,
                'paid_at'            => $now,
                'payment_reference'  => $reference,
                'payment_meta'       => $rawMeta,
            ]);

            $driver->update(['package_active_until' => $expiresAt]);

            return $package->fresh();
        });
    }

    public function markFailed(DriverPackage $package, string $reason, array $rawMeta = []): void
    {
        $package->update([
            'status'        => 'failed',
            'payment_meta'  => array_merge((array) $package->payment_meta, ['error' => $reason, 'raw' => $rawMeta]),
        ]);
    }

    /**
     * Süresi dolan paketleri kapat ve sürücüleri offline yap.
     * Cron her dakika çağırır.
     *
     * @return array{packages_expired:int, drivers_offlined:int}
     */
    public function sweepExpired(): array
    {
        $now = now();

        $expiredCount = DriverPackage::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'expired']);

        // Sürücülerin cache alanı temizlensin
        $offlinedCount = 0;
        Driver::query()
            ->whereNotNull('package_active_until')
            ->where('package_active_until', '<=', $now)
            ->chunkById(200, function ($drivers) use (&$offlinedCount) {
                foreach ($drivers as $driver) {
                    $driver->update([
                        'package_active_until' => null,
                        'availability_status'  => $driver->availability_status === 'online' ? 'offline' : $driver->availability_status,
                    ]);
                    $offlinedCount++;
                }
            });

        return [
            'packages_expired' => (int) $expiredCount,
            'drivers_offlined' => $offlinedCount,
        ];
    }
}
