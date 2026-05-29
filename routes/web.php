<?php

use App\Modules\Booking\Http\Controllers\CallController;
use App\Modules\Booking\Http\Controllers\CustomerPanelController;
use App\Modules\Booking\Http\Controllers\OtpDebugController;
use App\Modules\Booking\Http\Controllers\PhoneVerificationController;
use App\Modules\Booking\Http\Controllers\ReservationController;
use App\Modules\Booking\Http\Controllers\RideRequestController;
use App\Modules\Driver\Http\Controllers\DriverApplicationController;
use App\Modules\Driver\Http\Controllers\DriverPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'index'])->name('home');

Route::post('/rezervasyon', [ReservationController::class, 'store'])
    ->name('reservation.store');

Route::get('/rezervasyon/{publicId}', [ReservationController::class, 'confirmation'])
    ->name('reservation.confirmation');

// AJAX: canlı fiyat hesabı
Route::post('/api/calculate-fare', [ReservationController::class, 'calculateFare'])
    ->name('reservation.calculate-fare');

// AJAX: Canlı radar - hızlı sürücü talebi
Route::post('/api/quick-request', [ReservationController::class, 'quickRequest'])
    ->name('reservation.quick-request');

// AJAX: Yer arama proxy (Nominatim + 60 dk cache + İzmir viewbox)
Route::get('/api/search-places', [ReservationController::class, 'searchPlaces'])
    ->name('reservation.search-places');

// Sürücü başvuru sayfası
Route::get('/surucu-olun', [DriverApplicationController::class, 'show'])
    ->name('driver.apply');
Route::post('/surucu-olun', [DriverApplicationController::class, 'store'])
    ->name('driver.apply.store');

// Yolculuk Yapın - yolcu landing
Route::view('/yolculuk-yapin', 'ride.show')->name('ride.show');

// ─────────────────────────────────────────────────────────
// FAZ 3 — Ride Request / Accept akışı (müşteri tarafı)
// ─────────────────────────────────────────────────────────
Route::prefix('api/ride-requests')->name('ride_requests.')->group(function () {
    Route::get('/nearby',                [RideRequestController::class, 'nearby'])->name('nearby');
    Route::post('/',                     [RideRequestController::class, 'store'])->name('store');
    Route::get('/{publicId}',            [RideRequestController::class, 'show'])->name('show');
    Route::post('/{publicId}/cancel',    [RideRequestController::class, 'cancel'])->name('cancel');
    Route::post('/{publicId}/confirm',   [RideRequestController::class, 'confirm'])->name('confirm');
    Route::get('/{publicId}/messages',   [RideRequestController::class, 'messages'])->name('messages');
    Route::post('/{publicId}/messages',  [RideRequestController::class, 'sendMessage'])->name('messages.send');

    // WebRTC sesli görüşme — sinyalleşme polling, ses akışı P2P
    Route::post('/{publicId}/call/start',   [CallController::class, 'start'])->name('call.start');
    Route::post('/{publicId}/call/accept',  [CallController::class, 'accept'])->name('call.accept');
    Route::post('/{publicId}/call/end',     [CallController::class, 'end'])->name('call.end');
    Route::get('/{publicId}/call/state',    [CallController::class, 'state'])->name('call.state');
    Route::post('/{publicId}/call/signal',  [CallController::class, 'pushSignal'])->name('call.signal.push');
    Route::get('/{publicId}/call/signals',  [CallController::class, 'pullSignals'])->name('call.signal.pull');
});

// ─────────────────────────────────────────────────────────
// KORUMA KALKANI — Telefon OTP doğrulama (fake çağrı / sabotaj koruma)
// OTP doğrulanınca otomatik müşteri hesabı yaratılır + session'a login olur.
// ─────────────────────────────────────────────────────────
Route::post('/api/phone/send-otp',   [PhoneVerificationController::class, 'sendOtp'])->name('phone.send_otp');
Route::post('/api/phone/verify-otp', [PhoneVerificationController::class, 'verifyOtp'])->name('phone.verify_otp');

// GEÇİCİ: SMS provider bağlanana kadar admin için OTP görüntüleyici
Route::get('/admin-debug/otp', [OtpDebugController::class, 'show'])->name('admin.debug.otp');

// ─────────────────────────────────────────────────────────
// MÜŞTERİ PANELİ — telefon+OTP girişi, geçmiş, güven skoru
// ─────────────────────────────────────────────────────────
Route::get('/musteri-giris',         [CustomerPanelController::class, 'showLogin'])->name('customer.login');
Route::get('/musteri-paneli',        [CustomerPanelController::class, 'panel'])->name('customer.panel');
Route::get('/musteri-paneli/api/state', [CustomerPanelController::class, 'state'])->name('customer.api.state');
Route::post('/musteri-cikis',        [CustomerPanelController::class, 'logout'])->name('customer.logout');

// ─────────────────────────────────────────────────────────
// FAZ 3 — Sürücü Paneli (login + dashboard + actions)
// ─────────────────────────────────────────────────────────
Route::get('/surucu-giris',    [DriverPanelController::class, 'showLogin'])->name('driver.login');
Route::post('/surucu-giris',   [DriverPanelController::class, 'login'])->name('driver.login.submit');
Route::post('/surucu-cikis',   [DriverPanelController::class, 'logout'])->name('driver.logout');

// Not: `auth` middleware'i kullanmıyoruz — Laravel'in default 'login' named route'u
// yok ve Filament admin paneli farklı bir guard. Controller her metoda kendisi
// `currentDriver()` ile auth kontrolü yapıp ya redirect ya da 401 JSON döner.
Route::get('/surucu-paneli',                                 [DriverPanelController::class, 'panel'])->name('driver.panel');
Route::get('/surucu-paneli/api/state',                       [DriverPanelController::class, 'state'])->name('driver.api.state');
Route::post('/surucu-paneli/api/availability',               [DriverPanelController::class, 'setAvailability'])->name('driver.api.availability');
Route::post('/surucu-paneli/api/offers/{publicId}/accept',   [DriverPanelController::class, 'acceptOffer'])->name('driver.api.accept');
Route::post('/surucu-paneli/api/offers/{publicId}/reject',   [DriverPanelController::class, 'rejectOffer'])->name('driver.api.reject');
Route::post('/surucu-paneli/api/active/message',             [DriverPanelController::class, 'sendMessage'])->name('driver.api.message');
Route::post('/surucu-paneli/api/active/complete',            [DriverPanelController::class, 'completeRide'])->name('driver.api.complete');
Route::post('/surucu-paneli/api/active/arrived',             [DriverPanelController::class, 'markArrived'])->name('driver.api.arrived');
Route::post('/surucu-paneli/api/active/no-show',             [DriverPanelController::class, 'reportNoShow'])->name('driver.api.no_show');
