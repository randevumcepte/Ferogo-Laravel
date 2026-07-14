<?php

use App\Modules\Booking\Http\Controllers\CallController;
use App\Modules\Booking\Http\Controllers\CustomerPanelController;
use App\Modules\Booking\Http\Controllers\OtpDebugController;
use App\Modules\Booking\Http\Controllers\PhoneVerificationController;
use App\Modules\Booking\Http\Controllers\ReservationController;
use App\Modules\Booking\Http\Controllers\RideRequestController;
use App\Modules\Driver\Http\Controllers\DriverApplicationController;
use App\Modules\Driver\Http\Controllers\DriverOnboardingController;
use App\Modules\Driver\Http\Controllers\DriverPanelController;
use App\Modules\Driver\Http\Controllers\DriverReservationController;
use App\Modules\Legal\Http\Controllers\LegalConsentController;
use App\Modules\Marketing\Http\Controllers\AdReportController;
use App\Modules\Marketing\Models\Advertisement;
use App\Modules\Marketing\Models\AdEvent;
use App\Modules\Payment\Http\Controllers\DriverPackageController;
use App\Modules\Security\Http\Controllers\SecurityIncidentController;
use App\Modules\Security\Http\Controllers\PanicAlertController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', [ReservationController::class, 'index'])->name('home');

// Reklam tıklama takibi: tıklamayı sayar + detaylı olay kaydı yazar, sponsorun adresine yönlendirir.
// Opsiyonel ?la=&ln= (uygulamanın bildiği konum) ilçe kırılımı için kullanılır.
Route::get('/reklam/{advertisement}', function (Advertisement $advertisement, Request $request) {
    $advertisement->increment('clicks');

    [$anonId, $cookie] = AdEvent::anonId($request);
    $lat = is_numeric($request->query('la')) ? (float) $request->query('la') : null;
    $lng = is_numeric($request->query('ln')) ? (float) $request->query('ln') : null;
    try {
        AdEvent::record($advertisement, 'click', $request, $lat, $lng, $anonId);
    } catch (\Throwable $e) {
        // analitik yazımı başarısız olsa da yönlendirme çalışsın
    }

    $redirect = redirect()->away($advertisement->link_url ?: url('/'))->withCookie($cookie);
    $dist = AdEvent::districtFromLatLng($lat, $lng);
    if ($dist) {
        $redirect->withCookie(cookie('ferxgo_dist', $dist, 60 * 24 * 30));
    }
    return $redirect;
})->name('ad.click');

// Gösterim beacon'ı: reklam ekranda görününce JS buraya POST atar (görünürlük + kaba konum).
// CSRF muaf (bootstrap/app.php) — durum değiştirmez, sadece analitik yazar.
Route::post('/reklam/olay', function (Request $request) {
    $data = $request->validate([
        'ad'  => ['required', 'integer'],
        'type' => ['nullable', 'in:impression'],
        'lat' => ['nullable', 'numeric'],
        'lng' => ['nullable', 'numeric'],
    ]);
    $ad = Advertisement::find($data['ad']);
    if (! $ad) {
        return response()->json(['ok' => false], 404);
    }
    [$anonId, $cookie] = AdEvent::anonId($request);
    try {
        AdEvent::record($ad, 'impression', $request, $data['lat'] ?? null, $data['lng'] ?? null, $anonId);
    } catch (\Throwable $e) {
        // sessizce geç
    }
    $resp = response()->json(['ok' => true])->withCookie($cookie);
    // Kullanıcının ilçesini çereze yaz → sonraki sayfa açılışlarında bölgesel reklam gösterilir
    $dist = AdEvent::districtFromLatLng($data['lat'] ?? null, $data['lng'] ?? null);
    if ($dist) {
        $resp->withCookie(cookie('ferxgo_dist', $dist, 60 * 24 * 30)); // 30 gün
    }
    return $resp;
})->name('ad.event');

// Sponsor performans raporu (yazdırılabilir HTML → PDF). Yalnızca panel (admin) kullanıcıları.
Route::get('/reklam-rapor/{advertisement}', [AdReportController::class, 'show'])
    ->middleware('auth')
    ->name('ad.report');

// ─────────────────────────────────────────────────────────
// SEO — sitemap.xml (yalnızca herkese açık, indekslenebilir sayfalar)
// Panel / API / debug rotaları bilerek hariç (robots.txt'de de Disallow).
// ─────────────────────────────────────────────────────────
Route::get('/sitemap.xml', function () {
    $urls = [
        ['loc' => route('home'),                 'freq' => 'daily',   'priority' => '1.0'],
        ['loc' => route('ride.show'),            'freq' => 'weekly',  'priority' => '0.9'],
        ['loc' => route('driver.apply'),         'freq' => 'weekly',  'priority' => '0.8'],
        ['loc' => url('/izmir-havalimani-ulasim'), 'freq' => 'weekly', 'priority' => '0.8'],
        ['loc' => url('/izmir-uygun-ulasim'),      'freq' => 'weekly', 'priority' => '0.8'],
        ['loc' => url('/korsan-taksi-yasal-mi'),   'freq' => 'weekly', 'priority' => '0.7'],
        ['loc' => route('legal.ride-sharing'),   'freq' => 'monthly', 'priority' => '0.6'],
        ['loc' => route('legal.terms'),          'freq' => 'yearly',  'priority' => '0.3'],
        ['loc' => route('legal.kvkk'),           'freq' => 'yearly',  'priority' => '0.3'],
        ['loc' => route('legal.distance-sales'), 'freq' => 'yearly',  'priority' => '0.3'],
        ['loc' => route('legal.cookies'),        'freq' => 'yearly',  'priority' => '0.3'],
    ];

    // XML doğrudan burada üretilir (blade'de "<?xml" PHP açılış etiketi
    // sanılıp bazı sunucularda ParseError veriyordu — bu yol o sorunu tamamen önler).
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_QUOTES) . '</loc>' . "\n";
        $xml .= '    <changefreq>' . $u['freq'] . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $u['priority'] . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    $xml .= '</urlset>';

    return response($xml, 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::post('/rezervasyon', [ReservationController::class, 'store'])
    ->name('reservation.store');

Route::get('/rezervasyon/{publicId}', [ReservationController::class, 'confirmation'])
    ->name('reservation.confirmation');

// Müşteri rezervasyon listesi + iptal
Route::get('/rezervasyonlarim', [ReservationController::class, 'myReservations'])
    ->name('reservation.mine');
Route::post('/api/reservations/{publicId}/cancel', [ReservationController::class, 'cancel'])
    ->name('reservation.cancel');
// Karşılama — yolcu canlı durum sinyali (yola çıktım / geldim / gecikeceğim)
Route::post('/api/reservations/{publicId}/pax-status', [ReservationController::class, 'paxStatus'])
    ->middleware('throttle:30,1')
    ->name('reservation.pax-status');

// AJAX: canlı fiyat hesabı
Route::post('/api/calculate-fare', [ReservationController::class, 'calculateFare'])
    ->name('reservation.calculate-fare');

// AJAX: Canlı radar - hızlı sürücü talebi
Route::post('/api/quick-request', [ReservationController::class, 'quickRequest'])
    ->name('reservation.quick-request');

// AJAX: Yer arama proxy (Yandex Geosuggest → Photon → Nominatim, 60 dk cache)
Route::get('/api/search-places', [ReservationController::class, 'searchPlaces'])
    ->name('reservation.search-places');

// AJAX: Seçilen önerinin koordinatı (Yandex Geocoder; uri/text)
Route::get('/api/resolve-place', [ReservationController::class, 'resolvePlace'])
    ->name('reservation.resolve-place');

// AJAX: Ters geocode — koordinat → adres (sunucu proxy; tarayıcı nominatim'e gitmez)
Route::get('/api/reverse-geocode', [ReservationController::class, 'reverseGeocode'])
    ->name('reservation.reverse-geocode');

// Sürücü başvuru sayfası
Route::get('/surucu-olun', [DriverApplicationController::class, 'show'])
    ->name('driver.apply');
Route::post('/surucu-olun', [DriverApplicationController::class, 'store'])
    ->name('driver.apply.store');

// AJAX: kategori seçince marka listesi + marka seçince model listesi
Route::get('/api/driver-catalog/makes',  [DriverApplicationController::class, 'apiMakes'])
    ->name('driver.catalog.makes');
Route::get('/api/driver-catalog/models', [DriverApplicationController::class, 'apiModels'])
    ->name('driver.catalog.models');

// Yolculuk Yapın - yolcu landing
Route::view('/yolculuk-yapin', 'ride.show')->name('ride.show');

// ─────────────────────────────────────────────────────────
// SEO REHBER SAYFALARI — İzmir ulaşım niyetli aramaları yasal-güvenli
// çerçevede yakalar (paylaşımlı yolculuk alternatifi olarak konumlanır).
// ─────────────────────────────────────────────────────────
Route::view('/izmir-havalimani-ulasim', 'rehber.izmir-havalimani-ulasim')->name('rehber.havalimani');
Route::view('/izmir-uygun-ulasim',      'rehber.izmir-uygun-ulasim')->name('rehber.uygun');
Route::view('/korsan-taksi-yasal-mi',   'rehber.korsan-taksi-yasal-mi')->name('rehber.korsan-taksi');

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
    // Fiyat pazarlığı: müşteri sürücünün karşı teklifine yeni fiyat / kabul
    Route::post('/{publicId}/counter',      [RideRequestController::class, 'counter'])->name('counter');
    Route::post('/{publicId}/accept-price', [RideRequestController::class, 'acceptPrice'])->name('accept_price');
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
Route::post('/musteri-paneli/api/active/cancel', [CustomerPanelController::class, 'cancelActiveRide'])->name('customer.api.active_cancel');
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

// ─── Hesap-önce onboarding (Doğrulama Durumu) — web sayfası + paylaşımlı API (web+mobil) ───
Route::get('/surucu-paneli/dogrulama',                       [DriverOnboardingController::class, 'show'])->name('driver.onboarding');
Route::get('/surucu-paneli/api/onboarding/status',           [DriverOnboardingController::class, 'status'])->name('driver.onboarding.status');
Route::get('/surucu-paneli/api/onboarding/vehicle-models',   [DriverOnboardingController::class, 'vehicleModels'])->name('driver.onboarding.models');
Route::post('/surucu-paneli/api/onboarding/vehicle',         [DriverOnboardingController::class, 'saveVehicle'])->name('driver.onboarding.vehicle');
Route::post('/surucu-paneli/api/onboarding/photo',           [DriverOnboardingController::class, 'uploadPhoto'])->name('driver.onboarding.photo');
Route::post('/surucu-paneli/api/onboarding/document',        [DriverOnboardingController::class, 'uploadDocument'])->name('driver.onboarding.document');
Route::post('/surucu-paneli/api/onboarding/submit',          [DriverOnboardingController::class, 'submit'])->name('driver.onboarding.submit');

Route::get('/surucu-paneli/profil',                          [DriverPanelController::class, 'showProfile'])->name('driver.profile');
Route::post('/surucu-paneli/profil',                         [DriverPanelController::class, 'updateProfile'])->name('driver.profile.update');
Route::post('/surucu-paneli/api/vehicle-photo',              [DriverPanelController::class, 'uploadVehiclePhoto'])->name('driver.api.vehicle_photo');
Route::post('/surucu-paneli/api/document',                   [DriverPanelController::class, 'uploadDocument'])->name('driver.api.document.upload');
Route::post('/surucu-paneli/api/document/delete',            [DriverPanelController::class, 'deleteDocument'])->name('driver.api.document.delete');
Route::get('/surucu-paneli/api/state',                       [DriverPanelController::class, 'state'])->name('driver.api.state');
Route::post('/surucu-paneli/api/availability',               [DriverPanelController::class, 'setAvailability'])->name('driver.api.availability');
Route::post('/surucu-paneli/api/location',                   [DriverPanelController::class, 'updateLocation'])->name('driver.api.location');
Route::post('/surucu-paneli/api/women-only',                 [DriverPanelController::class, 'setWomenOnly'])->name('driver.api.women_only');
Route::post('/surucu-paneli/api/offers/{publicId}/accept',   [DriverPanelController::class, 'acceptOffer'])->name('driver.api.accept');
Route::post('/surucu-paneli/api/offers/{publicId}/counter',  [DriverPanelController::class, 'counterOffer'])->name('driver.api.counter');
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
// Panik sonrası CANLI konum güncelleme (kişi tarafı — public_id ile yetki, ULID tahmin edilemez)
Route::post('/api/panic/{publicId}/location', [PanicAlertController::class, 'updateLocation'])->name('security.panic.location');
// Admin panel sesli/görsel alarm dinleyicisi buradan açık alarmları çeker (sadece giriş yapmış admin)
Route::get('/admin/panic-poll', [PanicAlertController::class, 'poll'])
    ->middleware('auth')
    ->name('security.panic.poll');
// Operatör click-to-call (santral originate) — sadece giriş yapmış admin
Route::post('/admin/panic-call', [PanicAlertController::class, 'call'])
    ->middleware('auth')
    ->name('security.panic.call');

// Panik WebRTC sesli görüşme sinyalleşmesi — kişi (public_id) ↔ operatör (admin auth)
Route::post('/api/panic/{publicId}/signal',   [PanicAlertController::class, 'callerSignal'])->name('security.panic.caller_signal');
Route::get('/api/panic/{publicId}/signals',   [PanicAlertController::class, 'callerSignals'])->name('security.panic.caller_signals');
Route::post('/admin/panic-call/{id}/signal',  [PanicAlertController::class, 'operatorSignal'])->middleware('auth')->whereNumber('id')->name('security.panic.operator_signal');
Route::get('/admin/panic-call/{id}/signals',  [PanicAlertController::class, 'operatorSignals'])->middleware('auth')->whereNumber('id')->name('security.panic.operator_signals');

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
Route::view('/gizlilik-guvenlik',   'legal.privacy-security')->name('legal.privacy-security');
Route::view('/paylasimli-yolculuk', 'legal.ride-sharing')->name('legal.ride-sharing');

// Hukuki onay audit log endpoint'i (click-wrap consent kaydı)
Route::post('/api/legal-consent', [LegalConsentController::class, 'store'])
    ->name('legal.consent.store');
