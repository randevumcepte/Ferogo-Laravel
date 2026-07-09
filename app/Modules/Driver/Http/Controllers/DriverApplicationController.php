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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverApplicationController extends Controller
{
    public function __construct(private LegalConsentService $consents) {}

    /** 6 açılı araç fotoğrafı slot'ları (form input adları). */
    private const VEHICLE_PHOTO_SLOTS = [
        'front'          => 'Ön',
        'back'           => 'Arka',
        'left'           => 'Sol yan',
        'right'          => 'Sağ yan',
        'interior_front' => 'İç — ön koltuklar',
        'interior_back'  => 'İç — arka koltuklar',
    ];

    public function show()
    {
        return view('driver.apply', [
            'cities'          => City::where('is_active', true)->orderBy('sort_order')->get(),
            'categories'      => DriverCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'vehiclePhotoSlots' => self::VEHICLE_PHOTO_SLOTS,
        ]);
    }

    public function store(Request $request)
    {
        $cityIds     = City::where('is_active', true)->pluck('id')->toArray();
        $categoryIds = DriverCategory::where('is_active', true)->pluck('id')->toArray();

        // Kategoriye göre koşullu belge zorunluluğu:
        $categoryId   = (int) $request->input('driver_category_id');
        $categorySlug = DriverCategory::find($categoryId)?->slug;
        $isTaxi       = $categorySlug === 'sari_taksi';
        $isMotor      = $categorySlug === 'motosiklet';

        $rules = [
            // Kategori & kişisel
            'driver_category_id' => ['required', Rule::in($categoryIds)],
            'full_name'          => ['required', 'string', 'max:120'],
            'tc_no'              => ['required', 'digits:11'],
            'phone'              => ['required', 'string', 'max:32'],
            'email'              => ['required', 'email', 'max:255', 'unique:driver_applications,email'],
            'password'           => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
            'gender'             => ['required', Rule::in(['male', 'female'])],
            'birth_year'         => ['required', 'integer', 'min:1940', 'max:' . (date('Y') - 18)],
            'city_id'            => ['required', Rule::in($cityIds)],
            'license_class'      => ['required', Rule::in(['B', 'D', 'D1', 'E', 'A', 'A2'])],
            'experience_band'    => ['required', Rule::in(['under_1', '1_to_3', '3_to_5', '5_plus'])],

            // Araç bilgileri
            'vehicle_make_id'    => ['required', 'integer', 'exists:vehicle_makes,id'],
            'vehicle_model_id'   => ['required', 'integer', 'exists:vehicle_models,id'],
            'vehicle_year'       => ['required', 'integer', 'min:2000', 'max:' . (date('Y') + 1)],
            'vehicle_color'      => ['required', 'string', 'max:30'],
            'vehicle_capacity'   => ['required', 'integer', 'min:1', 'max:16'],
            'vehicle_plate'      => ['required', 'string', 'max:15'],

            // Kimlik & Ehliyet fotoğrafları — hepsi max 8MB, jpg/png/webp
            'selfie'             => ['required', 'image', 'max:8192'],
            'id_front'           => ['required', 'image', 'max:8192'],
            'id_back'            => ['required', 'image', 'max:8192'],
            'license_front'      => ['required', 'image', 'max:8192'],
            'license_back'       => ['required', 'image', 'max:8192'],

            // Araç fotoğrafları (6 açı)
            'vehicle_photo_front'          => ['required', 'image', 'max:8192'],
            'vehicle_photo_back'           => ['required', 'image', 'max:8192'],
            'vehicle_photo_left'           => ['required', 'image', 'max:8192'],
            'vehicle_photo_right'          => ['required', 'image', 'max:8192'],
            'vehicle_photo_interior_front' => ['required', 'image', 'max:8192'],
            'vehicle_photo_interior_back'  => ['required', 'image', 'max:8192'],

            // Belgeler (PDF de kabul)
            'registration_file'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'insurance_file'     => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'inspection_file'    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'criminal_record_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],

            // Kategori-özel belgeler
            'src_file'           => [$isTaxi ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'taksi_plaka_file'   => [$isTaxi ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'taksimetre_file'    => [$isTaxi ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'oda_kaydi_file'     => [$isTaxi ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'psychotechnic_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'helmet_file'        => [$isMotor ? 'required' : 'nullable', 'image', 'max:8192'],

            // Notlar
            'notes'              => ['nullable', 'string', 'max:1000'],

            // Onaylar
            'kvkk'               => ['accepted'],
            'terms'              => ['accepted'],
        ];

        $messages = [
            'driver_category_id.required' => 'Sürücü kategorisi (Otomobil / Sarı Taksi / Motosiklet) seçmelisin.',
            'tc_no.required'              => 'T.C. Kimlik No zorunludur.',
            'tc_no.digits'                => 'T.C. Kimlik No 11 haneli olmalı.',
            'vehicle_capacity.required'   => 'Aracın kaç yolcu aldığını seçmelisin.',
            'email.unique'                => 'Bu e-posta ile daha önce başvuru yapılmış.',
            'password.confirmed'          => 'Şifreler eşleşmiyor.',
            'kvkk.accepted'               => 'KVKK onayı zorunlu.',
            'terms.accepted'              => 'Hizmet Şartları ve Paylaşımlı Yolculuk model onayı zorunlu.',
            '*.required'                  => 'Bu alan zorunlu.',
            '*.image'                     => 'Bir fotoğraf (JPG / PNG / WEBP) yüklemelisin.',
            '*.max'                       => 'Dosya boyutu limiti aşıldı (max 8 MB fotoğraf, 10 MB belge).',
        ];

        $validated = $request->validate($rules, $messages);

        // Storage disk
        $disk = Storage::disk('public');
        $baseFolder = 'driver-applications/' . date('Y/m');

        // Basit dosya yükleme yardımcısı
        $upload = function (string $inputName) use ($request, $disk, $baseFolder): ?string {
            if (! $request->hasFile($inputName)) return null;
            return $request->file($inputName)->store($baseFolder, 'public');
        };

        // 6 açılı araç fotoğrafları → JSON dizi
        $vehiclePhotos = [];
        foreach (array_keys(self::VEHICLE_PHOTO_SLOTS) as $slot) {
            $path = $upload('vehicle_photo_' . $slot);
            if ($path) $vehiclePhotos[$slot] = $path;
        }

        // Marka + model + yıl → okunaklı 'vehicle_info' (Filament'te kolay görüntü)
        $vehicleInfo = trim(implode(' ', array_filter([
            VehicleMake::find($validated['vehicle_make_id'])?->name,
            VehicleModel::find($validated['vehicle_model_id'])?->name,
            $validated['vehicle_year'],
        ])));

        $application = DriverApplication::create([
            'full_name'          => $validated['full_name'],
            'tc_no'              => $validated['tc_no'],
            'phone'              => $validated['phone'],
            'email'              => strtolower(trim($validated['email'])),
            'password_hash'      => Hash::make($validated['password']),
            'city_id'            => $validated['city_id'],
            'birth_year'         => $validated['birth_year'],
            'gender'             => $validated['gender'],
            'license_class'      => $validated['license_class'],
            'driver_category_id' => $validated['driver_category_id'],
            'experience_band'    => $validated['experience_band'],
            'has_src'            => (bool) $request->boolean('has_src'),
            'has_vehicle'        => true,

            'vehicle_make_id'    => $validated['vehicle_make_id'],
            'vehicle_model_id'   => $validated['vehicle_model_id'],
            'vehicle_year'       => $validated['vehicle_year'],
            'vehicle_color'      => $validated['vehicle_color'],
            'vehicle_capacity'   => $validated['vehicle_capacity'],
            'vehicle_plate'      => strtoupper(preg_replace('/\s+/', ' ', $validated['vehicle_plate'])),
            'vehicle_info'       => $vehicleInfo,

            // Fotoğraflar + belgeler
            'selfie_file_path'          => $upload('selfie'),
            'id_front_file_path'        => $upload('id_front'),
            'id_back_file_path'         => $upload('id_back'),
            'license_front_file_path'   => $upload('license_front'),
            'license_back_file_path'    => $upload('license_back'),
            'vehicle_photos'            => $vehiclePhotos,
            'registration_file_path'    => $upload('registration_file'),
            'insurance_file_path'       => $upload('insurance_file'),
            'inspection_file_path'      => $upload('inspection_file'),
            'criminal_record_file_path' => $upload('criminal_record_file'),
            'src_file_path'             => $upload('src_file'),
            'taksi_plaka_file_path'     => $upload('taksi_plaka_file'),
            'taksimetre_file_path'      => $upload('taksimetre_file'),
            'oda_kaydi_file_path'       => $upload('oda_kaydi_file'),
            'psychotechnic_file_path'   => $upload('psychotechnic_file'),
            'helmet_file_path'          => $upload('helmet_file'),

            'notes'        => $validated['notes'] ?? null,
            'status'       => 'pending',
            'source'       => 'web',
            'ip_address'   => $request->ip(),
            'submitted_at' => now(),
        ]);

        // Hukuki onayları audit log'a yaz
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
