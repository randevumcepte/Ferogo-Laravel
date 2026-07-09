<?php

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\Driver;

/**
 * Hesap-önce (Martı modeli) sürücü onboarding durumunu hesaplar.
 *
 * Sorumluluk:
 *   - Her adımın (kişisel, araç bilgisi, araç fotoğrafları, ehliyet, selfie, SRC,
 *     adli sicil, psikoteknik, ruhsat, sigorta, muayene) tamamlanıp tamamlanmadığı
 *   - Eksik adımların listesi + tamamlanma yüzdesi
 *   - "İncelemeye hazır mı" (tüm zorunlu adımlar tam) — inceleme ancak bu true iken başlar
 *
 * Web ve mobil AYNI servisi (API üzerinden) kullanır → tek doğruluk kaynağı.
 */
class DriverOnboardingService
{
    /** Araç fotoğrafı için zorunlu 6 açı (key => etiket). */
    public const PHOTO_ANGLES = [
        'left'            => 'Sol yan',
        'front'           => 'Ön',
        'right'           => 'Sağ yan',
        'back'            => 'Arka',
        'interior_front'  => 'İç ön',
        'interior_back'   => 'İç arka',
    ];

    /**
     * Sürücünün onboarding durumunu döner.
     *
     * @return array{
     *   status: string,
     *   is_submitted: bool,
     *   is_ready_for_review: bool,
     *   percent: int,
     *   completed: int,
     *   total: int,
     *   missing: array<int, string>,
     *   steps: array<int, array{key:string,label:string,complete:bool,group:string}>
     * }
     */
    public function status(Driver $driver): array
    {
        $driver->loadMissing('user', 'currentVehicle');
        $v = $driver->currentVehicle;

        $steps = [
            $this->step('personal',        'Kişisel Bilgiler', 'kimlik',
                filled($driver->user?->name) && filled($driver->user?->phone) && $driver->city_id),

            $this->step('vehicle_info',     'Araç Bilgileri', 'arac',
                $v
                && filled($v->vehicle_type)
                && $v->vehicle_make_id
                && $v->vehicle_model_id
                && $v->year_of_manufacture
                && filled($v->color)
                && filled($v->plate)
                && $v->vehicle_class_id),

            $this->step('vehicle_photos',   'Araç Fotoğrafları', 'arac',
                $this->allAnglesPresent($v?->photo_angles)),

            $this->step('license',          'Ehliyet', 'belge',   filled($driver->license_file_path)),
            $this->step('selfie',           'Selfie Doğrulama', 'kimlik', filled($driver->selfie_file_path)),
            $this->step('src',              'SRC Belgesi', 'belge', filled($driver->src_file_path)),
            $this->step('criminal_record',  'Adli Sicil', 'belge', filled($driver->criminal_record_file_path)),
            $this->step('psychotechnic',    'Psikoteknik', 'belge', filled($driver->psychotechnic_file_path)),
            $this->step('registration',     'Ruhsat', 'belge',    filled($v?->registration_file_path)),
            $this->step('insurance',        'Sigorta', 'belge',   filled($driver->insurance_file_path)),
            $this->step('inspection',       'Muayene', 'belge',   filled($driver->inspection_file_path)),
        ];

        $total     = count($steps);
        $completed = count(array_filter($steps, fn ($s) => $s['complete']));
        $missing   = array_values(array_map(
            fn ($s) => $s['label'],
            array_filter($steps, fn ($s) => ! $s['complete']),
        ));

        $isReady     = $completed === $total;
        $isSubmitted = $driver->submitted_at !== null;

        return [
            'status'              => $this->deriveStatus($driver, $isReady, $isSubmitted),
            'is_submitted'        => $isSubmitted,
            'is_ready_for_review' => $isReady,
            'percent'             => $total ? (int) round($completed / $total * 100) : 0,
            'completed'           => $completed,
            'total'               => $total,
            'missing'             => $missing,
            'steps'               => $steps,
        ];
    }

    /** Tüm zorunlu adımlar tamam mı? (inceleme yalnızca bu true iken başlar) */
    public function isReadyForReview(Driver $driver): bool
    {
        return $this->status($driver)['is_ready_for_review'];
    }

    private function step(string $key, string $label, string $group, $complete): array
    {
        return ['key' => $key, 'label' => $label, 'group' => $group, 'complete' => (bool) $complete];
    }

    private function allAnglesPresent($angles): bool
    {
        if (! is_array($angles)) return false;
        foreach (array_keys(self::PHOTO_ANGLES) as $angle) {
            if (empty($angles[$angle])) return false;
        }
        return true;
    }

    /**
     * Kullanıcıya gösterilecek durum:
     *   incomplete     → eksik belge var, inceleme başlamadı
     *   pending_review → hepsi tam + gönderildi, admin incelemesi bekliyor
     *   approved / rejected / suspended → sonuç
     */
    private function deriveStatus(Driver $driver, bool $isReady, bool $isSubmitted): string
    {
        if ($driver->approval_status === 'approved')  return 'approved';
        if ($driver->approval_status === 'rejected')  return 'rejected';
        if ($driver->approval_status === 'suspended') return 'suspended';

        return $isSubmitted && $isReady ? 'pending_review' : 'incomplete';
    }
}
