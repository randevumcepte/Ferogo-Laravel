<?php

namespace App\Modules\Booking\Services;

use App\Models\User;
use App\Modules\Driver\Models\Driver;
use Illuminate\Support\Collection;

/**
 * "Favori şoförüm / tekrar onu çağır" — müşteri ↔ sürücü favori bağı.
 *
 * Hem web (CustomerPanelController, session auth) hem mobil
 * (CustomerRideController, Sanctum) bu servisi paylaşır; favori mantığı
 * tek yerde toplanır.
 */
class FavoriteDriverService
{
    /** Bir müşteri en fazla bu kadar sürücüyü favorileyebilir (abuse + UI koruması). */
    public const MAX_FAVORITES = 30;

    /**
     * Favoriye ekle/çıkar (idempotent toggle).
     *
     * @return array{ok:bool, favorited:bool, message:?string}
     */
    public function toggle(User $user, int $driverId): array
    {
        $driver = Driver::query()
            ->where('approval_status', 'approved')
            ->find($driverId);

        if (! $driver) {
            return ['ok' => false, 'favorited' => false, 'message' => 'Sürücü bulunamadı.'];
        }

        // Zaten favoriyse → çıkar.
        if ($this->isFavorite($user, $driverId)) {
            $user->favoriteDrivers()->detach($driverId);
            return ['ok' => true, 'favorited' => false, 'message' => 'Favorilerden çıkarıldı.'];
        }

        // Yeni favori → limit kontrolü.
        if ($user->favoriteDrivers()->count() >= self::MAX_FAVORITES) {
            return [
                'ok'        => false,
                'favorited' => true,
                'message'   => 'Favori sürücü limitine ulaştın (' . self::MAX_FAVORITES . '). Önce birini çıkar.',
            ];
        }

        // syncWithoutDetaching → yarış koşulunda çift kayıt yaratmaz (unique index zaten korur).
        $user->favoriteDrivers()->syncWithoutDetaching([$driverId]);

        return ['ok' => true, 'favorited' => true, 'message' => 'Favorilere eklendi.'];
    }

    /**
     * Favoriye ekle (idempotent). Zaten favoriyse sorun değil.
     *
     * @return array{ok:bool, favorited:bool, message:?string}
     */
    public function add(User $user, int $driverId): array
    {
        $driver = Driver::query()
            ->where('approval_status', 'approved')
            ->find($driverId);

        if (! $driver) {
            return ['ok' => false, 'favorited' => false, 'message' => 'Sürücü bulunamadı.'];
        }

        if ($this->isFavorite($user, $driverId)) {
            return ['ok' => true, 'favorited' => true, 'message' => 'Zaten favorilerinde.'];
        }

        if ($user->favoriteDrivers()->count() >= self::MAX_FAVORITES) {
            return [
                'ok'        => false,
                'favorited' => false,
                'message'   => 'Favori sürücü limitine ulaştın (' . self::MAX_FAVORITES . ').',
            ];
        }

        $user->favoriteDrivers()->syncWithoutDetaching([$driverId]);

        return ['ok' => true, 'favorited' => true, 'message' => 'Favorilere eklendi.'];
    }

    /**
     * Favoriden çıkar (idempotent).
     *
     * @return array{ok:bool, favorited:bool, message:?string}
     */
    public function remove(User $user, int $driverId): array
    {
        $user->favoriteDrivers()->detach($driverId);

        return ['ok' => true, 'favorited' => false, 'message' => 'Favorilerden çıkarıldı.'];
    }

    /** Bu sürücü bu müşterinin favorisi mi? */
    public function isFavorite(?User $user, int $driverId): bool
    {
        if (! $user) {
            return false;
        }

        return $user->favoriteDrivers()
            ->where('drivers.id', $driverId)
            ->exists();
    }

    /**
     * Müşterinin favori sürücü id listesi (UI'de kalpleri işaretlemek için hızlı set).
     *
     * @return array<int>
     */
    public function favoriteIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $user->favoriteDrivers()->pluck('drivers.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Favori-öncelikli dispatch için: müşterinin ŞU AN teklif gönderilebilecek
     * (dispatchable) favori sürücülerinin id'leri. MESAFE SINIRSIZ — favori online
     * olduğu sürece nerede olursa olsun teklif alır (çok uzaksa kendisi reddeder).
     *
     * Filtreler: approved + online + paket aktif + askıda değil + sınıf eşleşmesi.
     * Kadın-yolcu-only sürücüler yalnızca kadın müşteriye aday olur.
     *
     * @return array<int>
     */
    public function dispatchableFavoriteIds(?User $user, ?int $vehicleClassId = null, bool $customerIsFemale = false): array
    {
        if (! $user) {
            return [];
        }

        return $user->favoriteDrivers()
            ->where('approval_status', 'approved')
            ->where('availability_status', 'online')
            ->where('is_suspended', false)
            ->when(config('services.driver.enforce_packages', true), fn ($q) => $q
                ->whereNotNull('package_active_until')
                ->where('package_active_until', '>', now()))
            ->when(! $customerIsFemale, fn ($q) => $q->where('women_passengers_only', false))
            ->when($vehicleClassId, fn ($q) => $q->whereHas('currentVehicle', fn ($v) => $v
                ->where('vehicle_class_id', $vehicleClassId)))
            ->pluck('drivers.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Favori sürücüleri ilişkili user + araç ile yükle (kart listesi için).
     * En son favorilenen üstte.
     */
    public function listForUser(User $user): Collection
    {
        return $user->favoriteDrivers()
            ->with(['user:id,name,avatar', 'currentVehicle.vehicleClass'])
            ->orderByPivot('created_at', 'desc')
            ->get();
    }
}
