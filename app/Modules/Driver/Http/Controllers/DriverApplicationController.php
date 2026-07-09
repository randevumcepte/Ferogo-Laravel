<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Legal\Services\LegalConsentService;
use App\Modules\Shared\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Sürücü ÖN KAYIT (hesap-önce / Martı modeli).
 *
 * Ön kayıt formu hafif tutulur (kişisel + sürüş profili + hesap bilgisi). Gönderilince
 * DOĞRUDAN "beklemede" bir sürücü hesabı açılır ve sürücü otomatik giriş yapıp
 * "Doğrulama Durumu" (onboarding) ekranına düşer. Araç bilgisi, fotoğraflar ve tüm
 * belgeler orada adım adım toplanır; tümü tamamlanınca admin incelemesi başlar.
 */
class DriverApplicationController extends Controller
{
    public function __construct(
        private LegalConsentService $consents,
        private CustomerTrustService $trust,
    ) {}

    public function show()
    {
        // Zaten giriş yapmış sürücü → onboarding / panel
        if (Auth::guard('driver')->check()) {
            $driver = Driver::where('user_id', Auth::guard('driver')->id())->first();
            if ($driver) {
                return redirect()->route(
                    $driver->approval_status === 'approved' ? 'driver.panel' : 'driver.onboarding'
                );
            }
        }

        return view('driver.apply', [
            'cities' => City::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cityIds = City::where('is_active', true)->pluck('id')->toArray();

        $validated = $request->validate([
            'full_name'       => ['required', 'string', 'max:120'],
            'phone'           => ['required', 'string', 'max:32'],
            'email'           => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password'        => ['required', 'string', 'min:6', 'max:255'],
            'city_id'         => ['nullable', Rule::in($cityIds)],
            'birth_year'      => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 18)],
            'gender'          => ['required', Rule::in(['male', 'female'])],
            'license_class'   => ['required', Rule::in(['B', 'D', 'D1', 'E'])],
            'experience_band' => ['required', Rule::in(['under_1', '1_to_3', '3_to_5', '5_plus'])],
            'has_src'         => ['nullable', 'boolean'],
            'kvkk'            => ['accepted'],
            'terms'           => ['accepted'],
        ], [
            'email.unique'    => 'Bu e-posta ile zaten bir hesap var. Giriş yapmayı dene.',
            'gender.required' => 'Cinsiyet seçimi zorunlu.',
            'kvkk.accepted'   => 'KVKK onayını işaretlemen gerekiyor.',
            'terms.accepted'  => 'Hizmet Şartları ve Paylaşımlı Yolculuk model onayı zorunlu.',
        ]);

        $phone = $this->trust->normalizePhone($validated['phone']);

        $user = DB::transaction(function () use ($validated, $phone, $request) {
            $user = User::create([
                'name'     => $validated['full_name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone'    => $phone,
                'gender'   => $validated['gender'],
                'type'     => 'driver',
                'status'   => 'active', // giriş yapabilir; müşteriye görünürlük approval_status'a bağlı (gating)
            ]);

            Driver::create([
                'user_id'             => $user->id,
                'city_id'             => $validated['city_id'] ?? null,
                'license_class'       => $validated['license_class'],
                'experience_band'     => $validated['experience_band'],
                'commission_rate'     => 15.00,
                'availability_status' => 'offline',
                'approval_status'     => 'pending',   // + submitted_at=null → onboarding devam ediyor
                'rating'              => 5.00,
                'total_rides'         => 0,
            ]);

            // Ön kayıt audit kaydı (hukuki + operasyon izi)
            DriverApplication::create([
                'user_id'         => $user->id,
                'full_name'       => $validated['full_name'],
                'phone'           => $phone,
                'email'           => $validated['email'],
                'city_id'         => $validated['city_id'] ?? null,
                'birth_year'      => $validated['birth_year'] ?? null,
                'gender'          => $validated['gender'],
                'license_class'   => $validated['license_class'],
                'experience_band' => $validated['experience_band'],
                'has_src'         => (bool) ($validated['has_src'] ?? false),
                'has_vehicle'     => true,
                'status'          => 'pending',
                'source'          => 'web',
                'ip_address'      => $request->ip(),
            ]);

            return $user;
        });

        // ─── Hukuki onayları audit log'a yaz (mahkeme delili) ───
        $this->consents->recordMany(
            request: $request,
            items: [
                ['type' => 'driver_registration'],
                ['type' => 'terms'],
                ['type' => 'kvkk'],
                ['type' => 'ride_sharing'],
            ],
            acceptedVia: 'driver_registration',
            extraPayload: ['user_id' => $user->id],
        );

        // Otomatik giriş (sürücü guard) → onboarding
        Auth::guard('driver')->login($user, remember: true);
        $request->session()->regenerateToken();

        return redirect()->route('driver.onboarding')->with('onboarding_welcome', true);
    }
}
