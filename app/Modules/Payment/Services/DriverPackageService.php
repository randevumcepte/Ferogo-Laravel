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
     * Ödeme onaylandı → paketi aktive et + sürücünün cache alanını güncelle +
     * iyzico cardUserKey/cardToken bilgisini user'a yaz (saklı kart akışı için).
     *
     * Üst üste paket alındıysa süre eklenir.
     */
    public function markPaidAndActivate(DriverPackage $package, string $reference, array $rawMeta = []): DriverPackage
    {
        return DB::transaction(function () use ($package, $reference, $rawMeta) {
            $driver = $package->driver()->lockForUpdate()->with('user')->first();
            if (! $driver) {
                throw new \RuntimeException('Sürücü bulunamadı.');
            }

            $now = now();
            $startsAt = ($driver->package_active_until && $driver->package_active_until->isFuture())
                ? $driver->package_active_until
                : $now;

            $expiresAt = $startsAt->copy()->addHours($package->duration_hours);

            // iyzico response'unda kart bilgisi varsa user'a yaz + paket kaydına kopyala
            $cardUserKey = $rawMeta['cardUserKey'] ?? null;
            $cardToken   = $rawMeta['cardToken'] ?? null;
            $cardAlias   = $rawMeta['cardAssociation'] ?? null;  // VISA / MASTER_CARD
            $lastFour    = $rawMeta['lastFourDigits'] ?? null;

            if ($cardUserKey && $driver->user && empty($driver->user->iyzico_card_user_key)) {
                $driver->user->update(['iyzico_card_user_key' => $cardUserKey]);
            }

            $package->update([
                'status'             => 'active',
                'starts_at'          => $startsAt,
                'expires_at'         => $expiresAt,
                'paid_at'            => $now,
                'payment_reference'  => $reference,
                'payment_meta'       => $rawMeta,
                'card_token'         => $cardToken,
                'card_alias'         => $cardAlias,
                'card_last_four'     => $lastFour,
                // 3D HTML payload artık gerekmez, kapla
                'three_ds_html'      => null,
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
            'three_ds_html' => null,
        ]);
    }

    /**
     * Saklı kart ile 3D ödeme başlat. Sürücü 3D HTML'i görür, doğrulama sonrası
     * iyzico bizim callback URL'imize POST eder.
     *
     * $urlBuilder: paket oluşturulduktan sonra çağrılır, callback URL'ini döner
     * (paket id'sini içerebilmesi için lazy build).
     *
     * @return array{package: DriverPackage, html_content: string|null, error: string|null}
     */
    public function startSavedCardPayment(Driver $driver, string $type, string $cardToken, callable $urlBuilder): array
    {
        if (empty($driver->user?->iyzico_card_user_key)) {
            throw ValidationException::withMessages(['card' => 'Saklı kart bulunamadı. Yeni kart ile ödeme yap.']);
        }

        $package = $this->createPurchase($driver, $type);
        $callbackUrl = $urlBuilder($package);
        $gateway = GatewayFactory::make();

        $result = $gateway->init3dPayment(
            $package,
            $driver->user->iyzico_card_user_key,
            $cardToken,
            $callbackUrl,
        );

        if (! $result['success']) {
            $this->markFailed($package, $result['error'] ?? '3D başlatılamadı', $result['raw'] ?? []);
            return [
                'package'      => $package,
                'html_content' => null,
                'error'        => $result['error'] ?? '3D başlatılamadı',
            ];
        }

        $package->update([
            'three_ds_html'   => $result['html_content'],
            'conversation_id' => $result['conversation_id'],
            'card_token'      => $cardToken,
        ]);

        return [
            'package'      => $package,
            'html_content' => $result['html_content'],
            'error'        => null,
        ];
    }

    /**
     * Süresi dolan paketleri kapat ve sürücüleri offline yap. Cron her dakika çağırır.
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
