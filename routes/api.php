<?php

use App\Modules\Mobile\Http\Controllers\AuthController;
use App\Modules\Mobile\Http\Controllers\CallController;
use App\Modules\Mobile\Http\Controllers\CustomerRideController;
use App\Modules\Mobile\Http\Controllers\DeviceController;
use App\Modules\Mobile\Http\Controllers\DriverController;
use App\Modules\Mobile\Http\Controllers\NotificationController;
use App\Modules\Security\Http\Controllers\PanicAlertController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — /api/v1
|--------------------------------------------------------------------------
| Prefix: bootstrap/app.php'de apiPrefix: 'api/v1' ile sabitlendi.
| Auth: Sanctum bearer token (Authorization: Bearer ...).
| Cihaz bağlama: tüm authed istekler X-Device-Id header'ı zorunlu (TouchDevice middleware).
*/

// ─── PUBLIC (auth gerekmez) ────────────────────────────────────
Route::prefix('auth')->group(function () {
    // OTP send: dakikada 6 (Laravel built-in throttle ek katman; servis içi limit zaten var)
    Route::middleware('throttle:6,1')->post('customer/send-otp',   [AuthController::class, 'sendCustomerOtp']);
    Route::middleware('throttle:6,1')->post('customer/verify-otp', [AuthController::class, 'verifyCustomerOtp']);
    Route::middleware('throttle:10,1')->post('driver/login',       [AuthController::class, 'driverLogin']);
});

// ─── AUTHED — ortak (her iki rol) ──────────────────────────────
Route::middleware(['auth:sanctum', 'device'])->group(function () {
    Route::get('auth/me',     [AuthController::class, 'me']);
    Route::post('auth/logout',[AuthController::class, 'logout']);

    // Cihaz yönetimi
    Route::get('devices',                   [DeviceController::class, 'index']);
    Route::post('devices/push-token',       [DeviceController::class, 'registerPushToken']);
    Route::delete('devices/{id}',           [DeviceController::class, 'revoke'])->whereNumber('id');

    // Bildirim kutusu (inbox) — her iki rol
    Route::get('notifications',                 [NotificationController::class, 'index']);
    Route::get('notifications/unread-count',    [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all',       [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read',      [NotificationController::class, 'markRead'])->whereNumber('id');

    // Acil yardım (panik) — her iki rol, yolculuk sırasında
    Route::post('panic',                        [PanicAlertController::class, 'mobileTrigger']);
    Route::post('panic/{publicId}/location',    [PanicAlertController::class, 'updateLocation']);

    // WebRTC sesli görüşme — her iki rol (rol controller'da Sanctum'dan çözülür)
    Route::prefix('calls/{publicId}')->group(function () {
        Route::post('start',   [CallController::class, 'start']);
        Route::post('accept',  [CallController::class, 'accept']);
        Route::post('end',     [CallController::class, 'end']);
        Route::get('state',    [CallController::class, 'state']);
        Route::middleware('throttle:120,1')->post('signal',  [CallController::class, 'pushSignal']);
        Route::get('signals',  [CallController::class, 'pullSignals']);
    });
});

// ─── AUTHED — CUSTOMER ─────────────────────────────────────────
Route::middleware(['auth:sanctum', 'device', 'role:customer', 'ability:customer:*'])
    ->prefix('customer')
    ->group(function () {
        // Referans + arama + fiyat
        Route::get('bootstrap',          [CustomerRideController::class, 'bootstrap']);
        Route::get('places/search',      [CustomerRideController::class, 'searchPlaces']);
        Route::get('places/resolve',     [CustomerRideController::class, 'resolvePlace']);
        Route::get('route',              [CustomerRideController::class, 'route']);
        Route::post('fare/calculate',    [CustomerRideController::class, 'calculateFare']);

        // Sürücüler
        Route::get('drivers/nearby',           [CustomerRideController::class, 'nearbyDrivers']);
        Route::get('drivers/{id}/profile',     [CustomerRideController::class, 'driverProfile'])->whereNumber('id');

        // Favori sürücüler ("tekrar onu çağır")
        Route::get('favorites',                 [CustomerRideController::class, 'favorites']);
        Route::post('favorites/{driverId}',     [CustomerRideController::class, 'addFavorite'])->whereNumber('driverId');
        Route::delete('favorites/{driverId}',   [CustomerRideController::class, 'removeFavorite'])->whereNumber('driverId');

        // Ride request CRUD (talep + polling)
        Route::middleware('throttle:30,1')->post('ride-requests',                          [CustomerRideController::class, 'storeRequest']);
        Route::get('ride-requests/{publicId}',                                             [CustomerRideController::class, 'showRequest']);
        Route::post('ride-requests/{publicId}/cancel',                                     [CustomerRideController::class, 'cancelRequest']);
        Route::post('ride-requests/{publicId}/confirm',                                    [CustomerRideController::class, 'confirmRequest']);
        Route::post('ride-requests/{publicId}/visual-verify',                              [CustomerRideController::class, 'visualVerify']);
        // Auto/havuz akışı ("Hadi Gidelim"): eşleşen üye sürücüyü onayla/reddet
        Route::post('ride-requests/{publicId}/reconfirm',                                  [CustomerRideController::class, 'reconfirm']);
        // Fiyat pazarlığı: müşteri karşı teklif / kabul
        Route::middleware('throttle:30,1')->post('ride-requests/{publicId}/counter',       [CustomerRideController::class, 'counter']);
        Route::post('ride-requests/{publicId}/accept-price',                               [CustomerRideController::class, 'acceptPrice']);
        Route::get('ride-requests/{publicId}/messages',                                    [CustomerRideController::class, 'messages']);
        Route::middleware('throttle:30,1')->post('ride-requests/{publicId}/messages',      [CustomerRideController::class, 'sendMessage']);

        // Geçmiş
        Route::get('history', [CustomerRideController::class, 'history']);
    });

// ─── AUTHED — DRIVER ───────────────────────────────────────────
Route::middleware(['auth:sanctum', 'device', 'role:driver', 'ability:driver:*'])
    ->prefix('driver')
    ->group(function () {
        // Polling tek endpoint
        Route::get('state',                  [DriverController::class, 'state']);

        // Availability + konum
        Route::post('availability',          [DriverController::class, 'setAvailability']);
        Route::post('women-only',            [DriverController::class, 'setWomenOnly']);
        Route::post('service-radius',        [DriverController::class, 'setServiceRadius']);
        Route::middleware('throttle:30,1')->post('location', [DriverController::class, 'updateLocation']);

        // Teklifler
        Route::post('offers/{publicId}/accept',  [DriverController::class, 'acceptOffer']);
        Route::middleware('throttle:30,1')->post('offers/{publicId}/counter', [DriverController::class, 'counterOffer']);
        Route::post('offers/{publicId}/reject',  [DriverController::class, 'rejectOffer']);

        // Aktif yolculuk
        Route::post('active/arrived',  [DriverController::class, 'markArrived']);
        Route::post('active/start-code', [DriverController::class, 'startWithCode']);
        Route::post('active/no-show',  [DriverController::class, 'reportNoShow']);
        Route::post('active/complete', [DriverController::class, 'completeRide']);
        Route::post('active/cancel',   [DriverController::class, 'cancelActive']);
        Route::middleware('throttle:30,1')->post('active/message', [DriverController::class, 'sendMessage']);
    });
