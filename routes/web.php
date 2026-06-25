<?php

use App\Modules\Booking\Http\Controllers\CallController;
use App\Modules\Booking\Http\Controllers\CustomerPanelController;
use App\Modules\Booking\Http\Controllers\OtpDebugController;
use App\Modules\Booking\Http\Controllers\PhoneVerificationController;
use App\Modules\Booking\Http\Controllers\ReservationController;
use App\Modules\Booking\Http\Controllers\RideRequestController;
use App\Modules\Driver\Http\Controllers\DriverApplicationController;
use App\Modules\Driver\Http\Controllers\DriverPanelController;
use App\Modules\Driver\Http\Controllers\DriverReservationController;
use App\Modules\Legal\Http\Controllers\LegalConsentController;
use App\Modules\Payment\Http\Controllers\DriverPackageController;
use App\Modules\Security\Http\Controllers\SecurityIncidentController;
use App\Modules\Security\Http\Controllers\PanicAlertController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'index'])->name('home');

Route::post('/rezervasyon', [ReservationController::class, 'store'])
    ->name('reservation.store');

Route::get('/rezervasyon/{publicId}', [ReservationController::class, 'confirmation'])
    ->name('reservation.confirmation');

// Müşteri rezervasyon listesi + iptal
Route::get('/rezervasyonlarim', [ReservationController::class, 'myReservations'])
    ->name('reservation.mine');
Route::post('/api/reservations/{publicId}/cancel', [ReservationController::class, 'cancel'])
    ->name('reservation.cancel');

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
// Sürücü detay profili (müşteri "Seç"e basmadan önce inceler)
Route::get('/api/drivers/{driverId}/profile', [RideRequestController::class, 'driverProfile'])
    ->whereNumber('driverId')
    ->name('drivers.profile');

Route::prefix('api/ride-requests')->name('ride_requests.')->group(function () {
    Route::get('/nearby',                [RideRequestController::class, 'nearby'])->name('nearby');
    Route::post('/',                     [RideRequestController::class, 'store'])->name('store');
    Route::get('/{publicId}',            [RideRequestController::class, 'show'])->name('show');
    Route::post('/{publicId}/cancel',    [RideRequestController::class, 'cancel'])->name('cancel');
    Route::post('/{publicId}/confirm',   [RideRequestController::class, 'confirm'])->name('confirm');
    // Faz 3: müşteri havuz fallback sürücüsünü onay/red
    Route::post('/{publicId}/reconfirm', [RideRequestController::class, 'reconfirm'])->name('reconfirm');
    // Faz 6: müşteri ride başlangıcında sürücü/araç görsel doğrulaması
    Route::post('/{publicId}/visual-verify', [RideRequestController::class, 'visualVerify'])->name('visual_verify');
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
Route::get('/musteri-paneli/api/active-tracking', [CustomerPanelController::class, 'activeTracking'])->name('customer.api.tracking');
Route::post('/musteri-paneli/favori/{driverId}', [CustomerPanelController::class, 'toggleFavorite'])
    ->whereNumber('driverId')->name('customer.favorite.toggle');
Route::get('/musteri-paneli/profil',  [CustomerPanelController::class, 'showProfile'])->name('customer.profile');
Route::post('/musteri-paneli/profil', [CustomerPanelController::class, 'updateProfile'])->name('customer.profile.update');
Route::get('/musteri-paneli/profil/verilerimi-indir', [CustomerPanelController::class, 'downloadData'])->name('customer.profile.data');
Route::post('/musteri-paneli/profil/hesabi-sil',      [CustomerPanelController::class, 'deleteAccount'])->name('customer.profile.delete');
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
Route::get('/surucu-paneli/profil',                          [DriverPanelController::class, 'showProfile'])->name('driver.profile');
Route::post('/surucu-paneli/profil',                         [DriverPanelController::class, 'updateProfile'])->name('driver.profile.update');
Route::post('/surucu-paneli/api/vehicle-photo',              [DriverPanelController::class, 'uploadVehiclePhoto'])->name('driver.api.vehicle_photo');
Route::post('/surucu-paneli/api/document',                   [DriverPanelController::class, 'uploadDocument'])->name('driver.api.document.upload');
Route::post('/surucu-paneli/api/document/delete',            [DriverPanelController::class, 'deleteDocument'])->name('driver.api.document.delete');
Route::get('/surucu-paneli/api/state',                       [DriverPanelController::class, 'state'])->name('driver.api.state');
Route::post('/surucu-paneli/api/availability',               [DriverPanelController::class, 'setAvailability'])->name('driver.api.availability');
Route::post('/surucu-paneli/api/women-only',                 [DriverPanelController::class, 'setWomenOnly'])->name('driver.api.women_only');
Route::post('/surucu-paneli/api/offers/{publicId}/accept',   [DriverPanelController::class, 'acceptOffer'])->name('driver.api.accept');
Route::post('/surucu-paneli/api/offers/{publicId}/reject',   [DriverPanelController::class, 'rejectOffer'])->name('driver.api.reject');
Route::post('/surucu-paneli/api/active/message',             [DriverPanelController::class, 'sendMessage'])->name('driver.api.message');
Route::post('/surucu-paneli/api/active/complete',            [DriverPanelController::class, 'completeRide'])->name('driver.api.complete');
Route::post('/surucu-paneli/api/active/arrived',             [DriverPanelController::class, 'markArrived'])->name('driver.api.arrived');
Route::post('/surucu-paneli/api/active/no-show',             [DriverPanelController::class, 'reportNoShow'])->name('driver.api.no_show');
// Faz 5: tuzak soru + ride start akışı
Route::post('/surucu-paneli/api/active/boarding-question',   [DriverPanelController::class, 'openBoardingQuestion'])->name('driver.api.boarding_question');
Route::post('/surucu-paneli/api/active/boarding-confirm',    [DriverPanelController::class, 'confirmBoarding'])->name('driver.api.boarding_confirm');
Route::post('/surucu-paneli/api/active/start-ride',          [DriverPanelController::class, 'startRide'])->name('driver.api.start_ride');

// ─────────────────────────────────────────────────────────
// REZERVASYON DISPATCHER — sürücü Pazar + Aldıklarım
// ─────────────────────────────────────────────────────────
Route::get('/surucu-paneli/rezervasyonlar',                              [DriverReservationController::class, 'page'])->name('driver.reservations.page');
Route::get('/surucu-paneli/api/reservations/market',                     [DriverReservationController::class, 'market'])->name('driver.reservations.market');
Route::get('/surucu-paneli/api/reservations/mine',                       [DriverReservationController::class, 'mine'])->name('driver.reservations.mine');
Route::post('/surucu-paneli/api/reservations/{publicId}/accept',         [DriverReservationController::class, 'accept'])->name('driver.reservations.accept');
Route::post('/surucu-paneli/api/reservations/{publicId}/confirm',        [DriverReservationController::class, 'confirm'])->name('driver.reservations.confirm');
Route::post('/surucu-paneli/api/reservations/{publicId}/cancel',         [DriverReservationController::class, 'cancel'])->name('driver.reservations.cancel');

// ─────────────────────────────────────────────────────────
// FAZ 6 — Güvenlik olayı (security incident) + zorunlu doğrulama fotoğrafları
// ─────────────────────────────────────────────────────────
Route::get('/api/security-incidents/{publicId}',         [SecurityIncidentController::class, 'show'])->name('security.incident.show');
Route::post('/api/security-incidents/{publicId}/photo',  [SecurityIncidentController::class, 'uploadPhoto'])->name('security.incident.upload_photo');

// Faz 7 — Acil yardım (panic) butonu (her iki taraf)
Route::post('/api/panic', [PanicAlertController::class, 'trigger'])->name('security.panic.trigger');

// ─────────────────────────────────────────────────────────
// SÜRÜCÜ PAKET ABONELİĞİ — Martı TAG modeli (3 saatlik/günlük/haftalık/aylık)
// Paket aktif değilse radar'a düşmez, iş atanmaz.
// ─────────────────────────────────────────────────────────
Route::get('/surucu-paneli/paketler',                          [DriverPackageController::class, 'index'])->name('driver.packages.index');
Route::post('/surucu-paneli/paketler/satin-al',                [DriverPackageController::class, 'purchase'])->name('driver.packages.purchase');
Route::get('/surucu-paneli/paketler/basarili',                 [DriverPackageController::class, 'success'])->name('driver.packages.success');
Route::get('/surucu-paneli/paketler/{package}/basarisiz',      [DriverPackageController::class, 'failure'])
    ->whereNumber('package')->name('driver.packages.failure');
Route::get('/surucu-paneli/paketler/{package}/durum',          [DriverPackageController::class, 'status'])
    ->whereNumber('package')->name('driver.packages.status');
Route::get('/surucu-paneli/paketler/{package}/mock-checkout',  [DriverPackageController::class, 'mockCheckout'])
    ->whereNumber('package')->name('driver.packages.mock_checkout');

// PayTR sunucu-sunucu bildirim (CSRF muaf — bootstrap/app.php'de tanımlı).
// PayTR bu URL'e POST eder, hash kontrolü ile kimlik doğrulanır.
Route::post('/api/paytr/bildirim', [DriverPackageController::class, 'paytrNotification'])->name('paytr.notification');

// ─────────────────────────────────────────────────────────
// YASAL SAYFALAR (Martı dilinde — paylaşımlı yolculuk vurgusu)
// Click-wrap consent altyapısı + KVKK + mesafeli satış + çerez politikası
// ─────────────────────────────────────────────────────────
Route::view('/hizmet-sartlari',     'legal.terms')->name('legal.terms');
Route::view('/kvkk-aydinlatma',     'legal.kvkk')->name('legal.kvkk');
Route::view('/mesafeli-satis',      'legal.distance-sales')->name('legal.distance-sales');
Route::view('/cerez-politikasi',    'legal.cookies')->name('legal.cookies');
Route::view('/paylasimli-yolculuk', 'legal.ride-sharing')->name('legal.ride-sharing');

// Hukuki onay audit log endpoint'i (click-wrap consent kaydı)
Route::post('/api/legal-consent', [LegalConsentController::class, 'store'])
    ->name('legal.consent.store');
