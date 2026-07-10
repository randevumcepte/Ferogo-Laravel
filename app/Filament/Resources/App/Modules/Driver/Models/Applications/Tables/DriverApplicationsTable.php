<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Applications\Tables;

use App\Models\User;
use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleClass;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DriverApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('full_name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->formatStateUsing(fn ($state, $record) => $record->category
                        ? ($record->category->emoji . ' ' . $record->category->name)
                        : '—')
                    ->badge()
                    ->color(fn ($state, $record) => match ($record->category?->slug) {
                        'otomobil'    => 'info',
                        'sari_taksi'  => 'warning',
                        'motosiklet'  => 'success',
                        default       => 'gray',
                    }),

                TextColumn::make('gender')
                    ->label('Cinsiyet')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'female' => 'danger',
                        'male'   => 'info',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'female' => '👩 Kadın',
                        'male'   => '👨 Erkek',
                        default  => '—',
                    }),

                TextColumn::make('city.name')
                    ->label('Şehir')
                    ->placeholder('—'),

                TextColumn::make('license_class')
                    ->label('Ehl.')
                    ->badge(),

                TextColumn::make('experience_band')
                    ->label('Deneyim')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'under_1' => '<1 yıl',
                        '1_to_3'  => '1-3 yıl',
                        '3_to_5'  => '3-5 yıl',
                        '5_plus'  => '5+ yıl',
                        default   => $state ?? '—',
                    }),

                TextColumn::make('vehicle_info')
                    ->label('Araç')
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 240px;']),

                TextColumn::make('has_src')
                    ->label('SRC')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? 'Var' : 'Yok'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'contacted' => 'info',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Beklemede',
                        'contacted' => 'İletişime geçildi',
                        'approved'  => 'Onaylandı',
                        'rejected'  => 'Reddedildi',
                        default     => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Başvuru')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending'   => 'Beklemede',
                        'contacted' => 'İletişime geçildi',
                        'approved'  => 'Onaylandı',
                        'rejected'  => 'Reddedildi',
                    ]),
                SelectFilter::make('gender')
                    ->label('Cinsiyet')
                    ->options([
                        'female' => '👩 Kadın',
                        'male'   => '👨 Erkek',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('review')
                    ->label('🔍 Detaylı İncele')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedEye)
                    ->color('info')
                    ->modalHeading(fn (DriverApplication $a) => 'Başvuru İnceleme — ' . $a->full_name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat')
                    ->modalWidth('7xl')
                    ->schema([
                        Placeholder::make('detail')
                            ->label('')
                            ->content(fn (DriverApplication $a) => new HtmlString(self::buildDetailHtml($a))),
                    ]),

                Action::make('approve')
                    ->label('Onayla ve Hesap Aç')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    // Hesap-önce modelinde hesap ön kayıtta açılır (user_id dolu). Eski
                    // "hesap aç" aksiyonu yalnızca eski (hesabı olmayan) başvurularda görünür;
                    // yeni başvurular Sürücüler ekranından "İncele & Onayla" ile değerlendirilir.
                    ->visible(fn (DriverApplication $a) => $a->status !== 'approved' && ! $a->user_id)
                    ->schema([
                        TextInput::make('email')
                            ->label('Giriş e-postası')
                            ->email()
                            ->required()
                            ->default(fn (DriverApplication $a) => $a->email ?: 'surucu' . $a->id . '@ferxgo.com.tr')
                            ->helperText('Sürücü bu e-posta ile /surucu-giris ekranından giriş yapacak.'),
                        TextInput::make('password')
                            ->label('Geçici şifre')
                            ->required()
                            ->minLength(6)
                            ->default(fn () => Str::random(10))
                            ->helperText('Bu şifreyi sürücüye ilet — kendisi profil ekranından değiştirebilir.'),
                        Checkbox::make('women_passengers_only')
                            ->label('Sadece kadın yolcu al')
                            ->helperText('Sürücü kadınsa varsayılan olarak açılır. Sonradan kendi profilinden değiştirebilir.')
                            ->default(fn (DriverApplication $a) => $a->gender === 'female')
                            ->visible(fn (DriverApplication $a) => $a->gender === 'female'),
                    ])
                    ->action(function (DriverApplication $a, array $data) {
                        // Başvurudaki hazır şifre (sürücünün seçtiği) varsa onu kullan;
                        // yoksa modaldeki admin şifresi kullanılsın.
                        $userPasswordHash = $a->password_hash ?: Hash::make($data['password']);

                        try {
                        DB::transaction(function () use ($a, $data, $userPasswordHash) {
                            $user = User::create([
                                'name'     => $a->full_name,
                                'email'    => $data['email'],
                                'password' => $userPasswordHash,
                                'phone'    => preg_replace('/\s+/', '', $a->phone),
                                'tc_no'    => $a->tc_no,
                                'gender'   => $a->gender,
                                'type'     => 'driver',
                                'status'   => 'active',
                            ]);

                            $driver = Driver::create([
                                'user_id'               => $user->id,
                                'city_id'               => $a->city_id,
                                'license_class'         => $a->license_class,
                                'driver_category_id'    => $a->driver_category_id,
                                'experience_band'       => $a->experience_band,
                                'commission_rate'       => 15.00,
                                'availability_status'   => 'offline',
                                'approval_status'       => 'approved',
                                'approved_at'           => now(),
                                'rating'                => 5.00,
                                'total_rides'           => 0,
                                'women_passengers_only' => (bool) ($data['women_passengers_only'] ?? false),
                                // Belge path'lerini başvurudan sürücüye aktar (adminde onayl yapılmadan yüklendiği kabul edilir)
                                'license_file_path'         => $a->license_front_file_path,
                                'license_approved_at'       => now(),
                                'src_file_path'             => $a->src_file_path,
                                'src_approved_at'           => $a->src_file_path ? now() : null,
                                'psychotechnic_file_path'   => $a->psychotechnic_file_path,
                                'psychotechnic_approved_at' => $a->psychotechnic_file_path ? now() : null,
                                'criminal_record_file_path' => $a->criminal_record_file_path,
                                'criminal_record_approved_at' => now(),
                                'insurance_file_path'       => $a->insurance_file_path,
                                'insurance_approved_at'     => now(),
                                'inspection_file_path'      => $a->inspection_file_path,
                                'inspection_approved_at'    => now(),
                                'selfie_file_path'          => $a->selfie_file_path,
                                'selfie_approved_at'        => now(),
                            ]);

                            // Vehicle oluştur
                            if ($a->vehicle_make_id) {
                                $photos = collect($a->vehicle_photos ?? [])->values()->all();

                                // vehicle_class_id NOT NULL — kategoriye göre default sınıf.
                                // Admin isterse sonradan Filament > Filo > Araçlar'dan sınıfı değiştirebilir.
                                $defaultVehicleClass = VehicleClass::query()
                                    ->where('is_active', true)
                                    ->orderBy('id')
                                    ->first();

                                // Plaka çakışırsa (aynı plaka daha önce onaylanmış): mevcut vehicle'a driver'ı bağla
                                $existing = $a->vehicle_plate
                                    ? Vehicle::withTrashed()->where('plate', $a->vehicle_plate)->first()
                                    : null;

                                if ($existing) {
                                    // Aynı plaka zaten kayıtlı — mevcut aracı güncelleyip driver'a bağla
                                    if ($existing->trashed()) $existing->restore();
                                    $existing->update([
                                        'vehicle_make_id'          => $a->vehicle_make_id,
                                        'vehicle_model_id'         => $a->vehicle_model_id,
                                        'brand'                    => optional($a->vehicleMake)->name ?: 'Bilinmiyor',
                                        'model'                    => optional($a->vehicleModel)->name ?: 'Bilinmiyor',
                                        'year_of_manufacture'      => $a->vehicle_year,
                                        'color'                    => $a->vehicle_color,
                                        'capacity'                 => $a->vehicle_capacity,
                                        'status'                   => 'active',
                                        'photos'                   => $photos ?: $existing->photos,
                                        'registration_file_path'   => $a->registration_file_path ?: $existing->registration_file_path,
                                        'registration_approved_at' => now(),
                                    ]);
                                    $vehicle = $existing;
                                } else {
                                    $vehicle = Vehicle::create([
                                        'vehicle_class_id'   => $defaultVehicleClass?->id, // required
                                        'vehicle_make_id'    => $a->vehicle_make_id,
                                        'vehicle_model_id'   => $a->vehicle_model_id,
                                        'brand'              => optional($a->vehicleMake)->name ?: 'Bilinmiyor',
                                        'model'              => optional($a->vehicleModel)->name ?: 'Bilinmiyor',
                                        'year_of_manufacture'=> $a->vehicle_year ?: date('Y'),
                                        'color'              => $a->vehicle_color ?: 'Belirsiz',
                                        'capacity'           => $a->vehicle_capacity,
                                        'plate'              => $a->vehicle_plate,
                                        'status'             => 'active',
                                        'photos'             => $photos,
                                        'registration_file_path'   => $a->registration_file_path,
                                        'registration_approved_at' => now(),
                                    ]);
                                }

                                $driver->update(['current_vehicle_id' => $vehicle->id]);
                            }

                            $a->update([
                                'status'       => 'approved',
                                'user_id'      => $user->id,
                                'reviewed_at'  => now(),
                                'reviewed_by'  => auth()->id(),
                            ]);
                        });
                        } catch (\Throwable $ex) {
                            Log::error('[DriverApproval] transaction failed: ' . $ex->getMessage(), [
                                'application_id' => $a->id,
                                'trace' => $ex->getTraceAsString(),
                            ]);
                            Notification::make()
                                ->danger()
                                ->title('⚠ Onay başarısız')
                                ->body('Hata: ' . $ex->getMessage())
                                ->persistent()
                                ->send();
                            return;
                        }

                        // ─── Sürücüye onay + giriş bilgisi SMS'i ───
                        $smsStatus = 'gonderilmedi';
                        try {
                            $phone   = preg_replace('/\s+/', '', $a->phone);
                            $message = "FerXGo surucu basvurun onaylandi! ferxgo.com/surucu-giris - "
                                     . "E-posta: {$data['email']} - Sifre: {$data['password']} - "
                                     . "Giris yapip profil ekranindan sifreni degistir.";
                            $client  = app(VoiceTelekomClient::class);
                            $result  = $client->sendSingle($phone, $message);
                            $smsStatus = ($result['ok'] ?? false) ? 'gonderildi' : 'basarisiz';
                            if (! ($result['ok'] ?? false)) {
                                Log::warning('[DriverApproval] SMS gonderilemedi', [
                                    'application_id' => $a->id,
                                    'phone'          => $phone,
                                    'error'          => $result['message'] ?? '?',
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::error('[DriverApproval] SMS exception: ' . $e->getMessage(), [
                                'application_id' => $a->id,
                            ]);
                        }

                        $genderNote = $a->gender === 'female' ? ' 👩 Kadın sürücü' : ' 👨 Erkek sürücü';
                        $smsBadge   = $smsStatus === 'gonderildi'
                            ? '✓ SMS gönderildi'
                            : ($smsStatus === 'basarisiz' ? '⚠ SMS gönderilemedi (log\'a bak)' : '⚠ SMS provider hata');

                        Notification::make()
                            ->success()
                            ->title('Sürücü onaylandı' . $genderNote)
                            ->body('Giriş: ' . $data['email'] . ' · Şifre: ' . $data['password'] . ' · ' . $smsBadge)
                            ->persistent()
                            ->send();
                    }),

                Action::make('contacted')
                    ->label('İletişime geçildi')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPhoneArrowUpRight)
                    ->color('info')
                    ->visible(fn (DriverApplication $a) => $a->status === 'pending')
                    ->action(function (DriverApplication $a) {
                        $a->update(['status' => 'contacted']);
                        Notification::make()
                            ->success()
                            ->title('Kayıt güncellendi')
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reddet')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (DriverApplication $a) => ! in_array($a->status, ['approved', 'rejected'], true))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Red gerekçesi (dahili not)')
                            ->rows(3),
                    ])
                    ->action(function (DriverApplication $a, array $data) {
                        $note = trim(($a->notes ?? '') . "\n---\nRed: " . ($data['reason'] ?? '—'));
                        $a->update([
                            'status' => 'rejected',
                            'notes'  => $note,
                        ]);
                        Notification::make()
                            ->warning()
                            ->title('Başvuru reddedildi')
                            ->send();
                    }),
            ]);
    }

    /**
     * Başvurunun TÜM belge/fotoğraf/bilgilerini tek modal ekranda gösterir.
     * Admin buradan görsel inceleme yapar, sonra onayla/reddet basar.
     */
    private static function buildDetailHtml(DriverApplication $a): string
    {
        $a->loadMissing('city', 'category', 'vehicleMake', 'vehicleModel');

        $fileUrl = function (?string $path): ?string {
            if (! $path) return null;
            if (str_starts_with($path, 'http')) return $path;
            return Storage::disk('public')->url($path);
        };

        $imageBox = function (string $label, ?string $path) use ($fileUrl): string {
            $url = $fileUrl($path);
            if (! $url) {
                return '<div style="background:#1a1a1a;border:1px dashed #444;border-radius:8px;padding:16px;text-align:center;color:#888;font-size:12px;">' . e($label) . '<br><em>Yüklenmemiş</em></div>';
            }
            $isImage = preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $path);
            $content = $isImage
                ? '<img src="' . $url . '" style="width:100%;height:160px;object-fit:cover;border-radius:6px;">'
                : '<div style="height:160px;background:#111;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:32px;">📄 PDF</div>';
            return '<div style="background:#000;border:1px solid #333;border-radius:8px;padding:8px;">'
                . '<div style="font-size:11px;color:#aaa;margin-bottom:6px;font-weight:600;">' . e($label) . '</div>'
                . $content
                . '<a href="' . $url . '" target="_blank" style="display:block;margin-top:6px;font-size:11px;color:#F0C040;text-align:center;">Büyüt / İndir ↗</a>'
                . '</div>';
        };

        $vehiclePhotos = $a->vehicle_photos ?? [];
        $vehicleLabels = [
            'front'=>'Ön','back'=>'Arka','left'=>'Sol','right'=>'Sağ',
            'interior_front'=>'İç — Ön','interior_back'=>'İç — Arka',
        ];

        $html = '<div style="font-family:system-ui,sans-serif;font-size:13px;color:#e0e0e0;">';

        // Özet
        $html .= '<div style="background:rgba(240,192,64,0.06);border-left:3px solid #F0C040;padding:12px 16px;border-radius:6px;margin-bottom:16px;">';
        $html .= '<div style="font-size:15px;font-weight:700;color:#F0C040;">' . e($a->full_name) . '</div>';
        $html .= '<div style="color:#aaa;margin-top:4px;">';
        $html .= '🆔 T.C.: <strong style="color:#fff;">' . e($a->tc_no ?? '—') . '</strong>';
        $html .= ' · 📞 ' . e($a->phone) . ' · ✉️ ' . e($a->email ?? '—');
        $html .= ' · ' . ($a->gender === 'female' ? '👩 Kadın' : '👨 Erkek');
        $html .= ' · Doğum yılı: ' . e((string) $a->birth_year);
        $html .= ' · Şehir: ' . e($a->city?->name ?? '—');
        $html .= '</div>';
        if ($a->category) {
            $html .= '<div style="margin-top:8px;font-size:14px;">Kategori: <strong>' . e($a->category->emoji . ' ' . $a->category->name) . '</strong>';
            $html .= ' · Ehliyet: ' . e($a->license_class);
            $html .= ' · Deneyim: ' . e($a->experience_band);
            $html .= '</div>';
        }
        $html .= '</div>';

        // Kimlik & Selfie
        $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#888;margin:16px 0 10px;">Kimlik & Selfie</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">';
        $html .= $imageBox('Selfie', $a->selfie_file_path);
        $html .= $imageBox('Kimlik — Ön', $a->id_front_file_path);
        $html .= $imageBox('Kimlik — Arka', $a->id_back_file_path);
        $html .= '</div>';

        // Ehliyet
        $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#888;margin:16px 0 10px;">Ehliyet</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">';
        $html .= $imageBox('Ehliyet — Ön', $a->license_front_file_path);
        $html .= $imageBox('Ehliyet — Arka', $a->license_back_file_path);
        $html .= '</div>';

        // Araç bilgileri
        $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#888;margin:16px 0 10px;">Araç Bilgileri</h3>';
        $html .= '<div style="background:#000;border:1px solid #333;border-radius:8px;padding:12px 16px;color:#ddd;">';
        $html .= '<div><strong>' . e(($a->vehicleMake?->name ?? '') . ' ' . ($a->vehicleModel?->name ?? '')) . '</strong></div>';
        $html .= '<div style="color:#aaa;margin-top:4px;">Yıl: ' . e((string) $a->vehicle_year) . ' · Renk: ' . e($a->vehicle_color ?? '—') . ' · Kapasite: <strong>' . e((string) ($a->vehicle_capacity ?? '—')) . ' yolcu</strong> · Plaka: <strong style="color:#F0C040;">' . e($a->vehicle_plate ?? '—') . '</strong></div>';
        $html .= '</div>';

        // Araç fotoğrafları (6 açı)
        $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#888;margin:16px 0 10px;">Araç Fotoğrafları (6 açı)</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">';
        foreach ($vehicleLabels as $slot => $lbl) {
            $html .= $imageBox($lbl, $vehiclePhotos[$slot] ?? null);
        }
        $html .= '</div>';

        // Belgeler
        $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#888;margin:16px 0 10px;">Belgeler</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">';
        $html .= $imageBox('Araç Ruhsatı',     $a->registration_file_path);
        $html .= $imageBox('Trafik Sigortası', $a->insurance_file_path);
        $html .= $imageBox('Fenni Muayene',    $a->inspection_file_path);
        $html .= $imageBox('Adli Sicil',       $a->criminal_record_file_path);
        $html .= '</div>';

        // Kategori-özel belgeler
        if ($a->category?->slug === 'sari_taksi') {
            $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#F0C040;margin:16px 0 10px;">🚕 Sarı Taksi — Ek Belgeler</h3>';
            $html .= '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">';
            $html .= $imageBox('SRC-2',           $a->src_file_path);
            $html .= $imageBox('Taksi Plaka',     $a->taksi_plaka_file_path);
            $html .= $imageBox('Taksimetre',      $a->taksimetre_file_path);
            $html .= $imageBox('Oda Kaydı',       $a->oda_kaydi_file_path);
            $html .= '</div>';
        }
        if ($a->category?->slug === 'motosiklet') {
            $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#F0C040;margin:16px 0 10px;">🏍 Motosiklet — Ek Belge</h3>';
            $html .= '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">';
            $html .= $imageBox('Kask Fotoğrafı', $a->helmet_file_path);
            $html .= '</div>';
        }

        // Notlar
        if ($a->notes) {
            $html .= '<h3 style="font-size:12px;text-transform:uppercase;letter-spacing:0.15em;color:#888;margin:16px 0 10px;">Aday Notu</h3>';
            $html .= '<div style="background:#111;border:1px solid #333;border-radius:8px;padding:12px 16px;color:#ddd;">' . nl2br(e($a->notes)) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}
