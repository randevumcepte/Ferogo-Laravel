<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Legal\Services\LegalConsentService;
use App\Modules\Shared\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverApplicationController extends Controller
{
    public function __construct(private LegalConsentService $consents) {}

    public function show()
    {
        return view('driver.apply', [
            'cities' => City::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $cityIds = City::where('is_active', true)->pluck('id')->toArray();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'city_id' => ['nullable', Rule::in($cityIds)],
            'birth_year' => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 18)],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'license_class' => ['required', Rule::in(['B', 'D', 'D1', 'E'])],
            'experience_band' => ['required', Rule::in(['under_1', '1_to_3', '3_to_5', '5_plus'])],
            'has_src' => ['nullable', 'boolean'],
            'vehicle_info' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'kvkk' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'gender.required' => 'Cinsiyet seçimi zorunlu.',
            'gender.in'       => 'Geçersiz cinsiyet değeri.',
            'kvkk.accepted'   => 'KVKK onayını işaretlemen gerekiyor.',
            'terms.accepted'  => 'Hizmet Şartları ve Paylaşımlı Yolculuk model onayı zorunlu.',
        ]);

        $application = DriverApplication::create([
            ...collect($validated)->except(['terms'])->all(),
            'has_src' => (bool) ($validated['has_src'] ?? false),
            'has_vehicle' => true,
            'status' => 'pending',
            'source' => 'web',
            'ip_address' => $request->ip(),
        ]);

        // ─── Hukuki onayları audit log'a yaz (mahkeme delili) ───
        // 4 ayrı kayıt: driver_registration, terms, kvkk, ride_sharing
        $this->consents->recordMany(
            request: $request,
            items: [
                ['type' => 'driver_registration'],
                ['type' => 'terms'],
                ['type' => 'kvkk'],
                ['type' => 'ride_sharing'],
            ],
            acceptedVia: 'driver_registration',
            extraPayload: ['application_id' => $application->id],
        );

        return redirect()
            ->route('driver.apply')
            ->with('application_success', true);
    }
}
