<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\DriverCategory;
use App\Modules\Legal\Services\LegalConsentService;
use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\VehicleMake;
use App\Modules\Vehicle\Models\VehicleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverApplicationController extends Controller
{
    public function __construct(private LegalConsentService $consents) {}

    public function show()
    {
        return view('driver.apply', [
            'cities'     => City::where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => DriverCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $cityIds     = City::where('is_active', true)->pluck('id')->toArray();
        $categoryIds = DriverCategory::where('is_active', true)->pluck('id')->toArray();

        $validated = $request->validate([
            'full_name'          => ['required', 'string', 'max:120'],
            'phone'              => ['required', 'string', 'max:32'],
            'email'              => ['nullable', 'email', 'max:255'],
            'city_id'            => ['nullable', Rule::in($cityIds)],
            'birth_year'         => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 18)],
            'gender'             => ['required', Rule::in(['male', 'female'])],
            'driver_category_id' => ['required', Rule::in($categoryIds)],
            'license_class'      => ['required', Rule::in(['B', 'D', 'D1', 'E', 'A', 'A2'])],
            'experience_band'    => ['required', Rule::in(['under_1', '1_to_3', '3_to_5', '5_plus'])],
            'has_src'            => ['nullable', 'boolean'],
            'vehicle_make_id'    => ['nullable', 'integer', 'exists:vehicle_makes,id'],
            'vehicle_model_id'   => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'vehicle_year'       => ['nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'vehicle_color'      => ['nullable', 'string', 'max:30'],
            'vehicle_info'       => ['nullable', 'string', 'max:255'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'kvkk'               => ['accepted'],
            'terms'              => ['accepted'],
        ], [
            'gender.required'              => 'Cinsiyet seçimi zorunlu.',
            'gender.in'                    => 'Geçersiz cinsiyet değeri.',
            'driver_category_id.required'  => 'Sürücü kategorisi seçmelisin (Otomobil / Sarı Taksi / Motosiklet).',
            'driver_category_id.in'        => 'Geçersiz kategori.',
            'kvkk.accepted'                => 'KVKK onayını işaretlemen gerekiyor.',
            'terms.accepted'               => 'Hizmet Şartları ve Paylaşımlı Yolculuk model onayı zorunlu.',
        ]);

        // vehicle_info yoksa marka+model+yıl'dan otomatik oluştur (Filament tablosunda okunaklı olsun)
        if (empty($validated['vehicle_info'])) {
            $parts = [];
            if (! empty($validated['vehicle_make_id'])) {
                $parts[] = VehicleMake::find($validated['vehicle_make_id'])?->name;
            }
            if (! empty($validated['vehicle_model_id'])) {
                $parts[] = VehicleModel::find($validated['vehicle_model_id'])?->name;
            }
            if (! empty($validated['vehicle_year'])) {
                $parts[] = $validated['vehicle_year'];
            }
            $validated['vehicle_info'] = trim(implode(' ', array_filter($parts))) ?: null;
        }

        $application = DriverApplication::create([
            ...collect($validated)->except(['terms'])->all(),
            'has_src'     => (bool) ($validated['has_src'] ?? false),
            'has_vehicle' => true,
            'status'      => 'pending',
            'source'      => 'web',
            'ip_address'  => $request->ip(),
        ]);

        // Hukuki onayları audit log
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

    /**
     * AJAX: kategori seçilince o kategoriye uygun markaları getir.
     * GET /api/driver-catalog/makes?category=motosiklet
     */
    public function apiMakes(Request $request): JsonResponse
    {
        $category = $request->query('category');
        if (! $category || ! in_array($category, ['otomobil', 'sari_taksi', 'motosiklet'], true)) {
            return response()->json(['makes' => []]);
        }

        $makes = VehicleMake::query()
            ->where('is_active', true)
            ->whereJsonContains('applicable_categories', $category)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->all();

        return response()->json(['makes' => $makes]);
    }

    /**
     * AJAX: marka + kategori seçilince modelleri getir.
     * GET /api/driver-catalog/models?make=15&category=motosiklet
     */
    public function apiModels(Request $request): JsonResponse
    {
        $makeId   = (int) $request->query('make', 0);
        $category = $request->query('category');
        if (! $makeId || ! $category) {
            return response()->json(['models' => []]);
        }

        $models = VehicleModel::query()
            ->where('vehicle_make_id', $makeId)
            ->where('category_slug', $category)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->all();

        return response()->json(['models' => $models]);
    }
}
