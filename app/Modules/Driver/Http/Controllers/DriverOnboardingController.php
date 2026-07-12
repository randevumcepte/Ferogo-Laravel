<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Services\DriverOnboardingService;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleClass;
use App\Modules\Vehicle\Models\VehicleMake;
use App\Modules\Vehicle\Models\VehicleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Hesap-önce (Martı modeli) sürücü doğrulama/onboarding akışı.
 *
 * Ön kayıttan sonra sürücü buraya düşer; adım adım bilgileri tamamlar:
 *   kişisel · araç bilgisi · araç fotoğrafları · ehliyet · selfie · SRC · adli sicil
 *   · psikoteknik · ruhsat · sigorta · muayene
 *
 * Her adım ANINDA kaydedilir (kısmi yükleme serbest). İnceleme yalnızca TÜM
 * belgeler tamamlanıp "submit" edilince başlar (submitted_at set edilir).
 *
 * Aynı endpoint'ler hem web (AJAX) hem mobil (Flutter) tarafından kullanılır.
 */
class DriverOnboardingController extends Controller
{
    /** Driver'a (bir kolona) yazılan belge tipleri. */
    private const DRIVER_DOCS = [
        'license'         => ['column' => 'license_file_path',         'approved' => 'license_approved_at',         'expires' => 'license_expires_at'],
        'src'             => ['column' => 'src_file_path',             'approved' => 'src_approved_at',             'expires' => 'src_expires_at'],
        'psychotechnic'   => ['column' => 'psychotechnic_file_path',   'approved' => 'psychotechnic_approved_at',   'expires' => 'psychotechnic_test_at'],
        'criminal_record' => ['column' => 'criminal_record_file_path', 'approved' => 'criminal_record_approved_at', 'expires' => 'criminal_record_at'],
        'insurance'       => ['column' => 'insurance_file_path',       'approved' => 'insurance_approved_at',       'expires' => 'insurance_expires_at'],
        'inspection'      => ['column' => 'inspection_file_path',      'approved' => 'inspection_approved_at',       'expires' => 'inspection_expires_at'],
        'selfie'          => ['column' => 'selfie_file_path',          'approved' => 'selfie_approved_at',          'expires' => null],
    ];

    public function __construct(private DriverOnboardingService $onboarding) {}

    // ── Web sayfası ─────────────────────────────────────────────

    /** GET /surucu-paneli/dogrulama — "Doğrulama Durumu" ekranı. */
    public function show(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        // Zaten onaylı → tam panele
        if ($driver->approval_status === 'approved') {
            return redirect()->route('driver.panel');
        }

        return view('driver.onboarding', [
            'driver'         => $driver,
            'onboarding'     => $this->onboarding->status($driver),
            'vehicle'        => $driver->currentVehicle,
            'vehicleClasses' => VehicleClass::where('is_active', true)->orderBy('sort_order')->get(['id', 'slug', 'name']),
            'makes'          => VehicleMake::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ── Paylaşımlı API (web AJAX + mobil) ───────────────────────

    /** GET onboarding/status → tamamlanma durumu + eksikler. */
    public function status(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        return response()->json(['ok' => true, 'onboarding' => $this->onboarding->status($driver)]);
    }

    /** GET onboarding/vehicle-models?make_id= → seçilen markanın modelleri (bağımlı dropdown). */
    public function vehicleModels(Request $request): JsonResponse
    {
        $makeId = (int) $request->query('make_id');
        $models = VehicleModel::where('vehicle_make_id', $makeId)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['ok' => true, 'models' => $models]);
    }

    /** POST onboarding/vehicle → araç bilgisi kaydet (yoksa oluştur + sürücüye bağla). */
    public function saveVehicle(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $data = $request->validate([
            'vehicle_type'     => ['required', 'string', 'max:30'],
            'vehicle_make_id'  => ['required', 'integer', 'exists:vehicle_makes,id'],
            'vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'year'             => ['required', 'integer', 'between:1990,' . (date('Y') + 1)],
            'color'            => ['required', 'string', 'max:30'],
            'plate'            => ['required', 'string', 'max:20'],
            // Tek-kademe model: sürücü sınıf seçmez; sunucu aktif sınıfı atar.
            'vehicle_class_id' => ['nullable', 'integer', 'exists:vehicle_classes,id'],
        ]);

        // Sürücü sınıf seçmediğinde aktif varsayılan sınıfa düş.
        if (empty($data['vehicle_class_id'])) {
            $data['vehicle_class_id'] = VehicleClass::activeDefault()?->id;
        }

        // Model gerçekten seçilen markaya mı ait?
        $model = VehicleModel::where('id', $data['vehicle_model_id'])
            ->where('vehicle_make_id', $data['vehicle_make_id'])
            ->first();
        if (! $model) {
            return response()->json(['ok' => false, 'message' => 'Seçilen model bu markaya ait değil.'], 422);
        }
        $make  = VehicleMake::find($data['vehicle_make_id']);
        $plate = strtoupper(trim($data['plate']));

        // Plaka benzersizliği (kendi aracı hariç)
        $plateExists = Vehicle::where('plate', $plate)
            ->when($driver->current_vehicle_id, fn ($q) => $q->where('id', '!=', $driver->current_vehicle_id))
            ->exists();
        if ($plateExists) {
            return response()->json(['ok' => false, 'message' => 'Bu plaka zaten kayıtlı.'], 422);
        }

        $attrs = [
            'vehicle_type'        => $data['vehicle_type'],
            'vehicle_make_id'     => $make->id,
            'vehicle_model_id'    => $model->id,
            'brand'               => $make->name,   // görüntü + geri uyum
            'model'               => $model->name,
            'year_of_manufacture' => $data['year'],
            'color'               => $data['color'],
            'plate'               => $plate,
            // Sürücü sınıfı ÖNERİR; admin incelemede onaylar → class_confirmed_at reset
            'vehicle_class_id'    => $data['vehicle_class_id'],
            'class_confirmed_at'  => null,
        ];

        $vehicle = $driver->currentVehicle;
        if ($vehicle) {
            $vehicle->update($attrs);
        } else {
            $vehicle = Vehicle::create($attrs + [
                'tenant_id' => $driver->tenant_id,
                'status'    => 'pending',
            ]);
            $driver->update(['current_vehicle_id' => $vehicle->id]);
        }

        // Araç değişince önceki onay bilgisi geçersiz → yeniden incele
        $this->resetSubmission($driver);

        return response()->json(['ok' => true, 'onboarding' => $this->onboarding->status($driver->fresh())]);
    }

    /** POST onboarding/photo → tek açı araç fotoğrafı (angle=left|front|...). */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);
        if (! $driver->currentVehicle) {
            return response()->json(['ok' => false, 'message' => 'Önce araç bilgilerini kaydet.'], 422);
        }

        $data = $request->validate([
            'angle' => ['required', 'string', 'in:' . implode(',', array_keys(DriverOnboardingService::PHOTO_ANGLES))],
            'photo' => ['required', 'image', 'max:8192'],
        ]);

        $vehicle = $driver->currentVehicle;
        $angles  = is_array($vehicle->photo_angles) ? $vehicle->photo_angles : [];

        // Eski açı dosyasını temizle
        if (! empty($angles[$data['angle']]) && ! str_starts_with($angles[$data['angle']], 'http')) {
            Storage::disk('public')->delete($angles[$data['angle']]);
        }

        $path = $request->file('photo')->store('vehicle-photos/' . $vehicle->id, 'public');
        $angles[$data['angle']] = $path;
        $vehicle->update(['photo_angles' => $angles]);

        $this->resetSubmission($driver);

        return response()->json([
            'ok'         => true,
            'angle'      => $data['angle'],
            'url'        => asset('storage/' . $path),
            'onboarding' => $this->onboarding->status($driver->fresh()),
        ]);
    }

    /** POST onboarding/document → belge yükle (ehliyet, selfie, SRC, ruhsat ...). */
    public function uploadDocument(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $allTypes = array_merge(array_keys(self::DRIVER_DOCS), ['registration']);
        $data = $request->validate([
            'type'    => ['required', 'string', 'in:' . implode(',', $allTypes)],
            'file'    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'expires' => ['nullable', 'date'],
        ]);

        $type = $data['type'];

        // Ruhsat araca yazılır
        if ($type === 'registration') {
            if (! $driver->currentVehicle) {
                return response()->json(['ok' => false, 'message' => 'Önce araç bilgilerini kaydet.'], 422);
            }
            $vehicle = $driver->currentVehicle;
            if ($vehicle->registration_file_path && ! str_starts_with($vehicle->registration_file_path, 'http')) {
                Storage::disk('public')->delete($vehicle->registration_file_path);
            }
            $path = $request->file('file')->store('driver-documents/' . $driver->id, 'public');
            $vehicle->update(['registration_file_path' => $path, 'registration_approved_at' => null]);
            $this->resetSubmission($driver);

            return response()->json(['ok' => true, 'url' => asset('storage/' . $path), 'onboarding' => $this->onboarding->status($driver->fresh())]);
        }

        $cfg = self::DRIVER_DOCS[$type];
        if ($driver->{$cfg['column']} && ! str_starts_with($driver->{$cfg['column']}, 'http')) {
            Storage::disk('public')->delete($driver->{$cfg['column']});
        }
        $path = $request->file('file')->store('driver-documents/' . $driver->id, 'public');

        $update = [$cfg['column'] => $path];
        if ($cfg['approved']) $update[$cfg['approved']] = null;   // yeni dosya → yeniden onay
        if ($cfg['expires'] && $request->filled('expires')) $update[$cfg['expires']] = $data['expires'];
        $driver->update($update);

        $this->resetSubmission($driver);

        return response()->json(['ok' => true, 'url' => asset('storage/' . $path), 'onboarding' => $this->onboarding->status($driver->fresh())]);
    }

    /**
     * POST onboarding/submit → incelemeye gönder.
     * Eksik varsa 422 + eksik liste döner (inceleme BAŞLAMAZ).
     */
    public function submit(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $status = $this->onboarding->status($driver);
        if (! $status['is_ready_for_review']) {
            return response()->json([
                'ok'      => false,
                'code'    => 'incomplete',
                'message' => 'Eksik evrakınız var. İnceleme, tüm belgeler yüklendiğinde başlar.',
                'missing' => $status['missing'],
                'onboarding' => $status,
            ], 422);
        }

        $driver->update(['submitted_at' => now()]);

        return response()->json([
            'ok'      => true,
            'message' => 'Belgeleriniz eksiksiz alındı. İnceleme ekibimiz başvurunuzu incelemeye başladı. Sonuç size bildirilecek.',
            'onboarding' => $this->onboarding->status($driver->fresh()),
        ]);
    }

    // ── yardımcılar ─────────────────────────────────────────────

    /** Bir belge/araç değiştiğinde: daha önce gönderildiyse incelemeyi sıfırla. */
    private function resetSubmission(Driver $driver): void
    {
        if ($driver->submitted_at !== null && $driver->approval_status !== 'approved') {
            $driver->update(['submitted_at' => null]);
        }
    }

    private function currentDriver(): ?Driver
    {
        $user = Auth::guard('driver')->user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }
}
