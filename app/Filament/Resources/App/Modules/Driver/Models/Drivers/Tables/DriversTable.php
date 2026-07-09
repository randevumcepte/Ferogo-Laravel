<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Tables;

use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Services\DriverOnboardingService;
use App\Modules\Payment\Models\DriverPackage;
use App\Modules\Vehicle\Models\VehicleClass;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('user.name')
                    ->label('Sürücü')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

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
                    })
                    ->toggleable(),

                TextColumn::make('user.phone')
                    ->label('Telefon')
                    ->copyable(),

                TextColumn::make('user.email')
                    ->label('Giriş E-posta')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope')
                    ->tooltip('Sürücünün /surucu-giris ekranından giriş yaptığı e-posta')
                    ->placeholder('—'),

                TextColumn::make('user.gender')
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
                    })
                    ->toggleable(),

                TextColumn::make('women_passengers_only')
                    ->label('Kadın Yolcu')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? '✓ Aktif' : '—')
                    ->tooltip('Sadece kadın yolcu kabul ediyor mu')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.last_login_at')
                    ->label('Son Giriş')
                    ->since()
                    ->placeholder('Hiç girmedi')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('city.name')
                    ->label('Şehir')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('availability_status')
                    ->label('Müsaitlik')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'busy' => 'warning',
                        'offline' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'Müsait',
                        'busy' => 'Yolculukta',
                        'offline' => 'Çevrimdışı',
                        default => $state,
                    }),

                TextColumn::make('approval_status')
                    ->label('Onay')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Onaylı',
                        'pending' => 'Beklemede',
                        'rejected' => 'Reddedildi',
                        'suspended' => 'Askıya',
                        default => $state,
                    }),

                TextColumn::make('submitted_at')
                    ->label('İnceleme')
                    ->badge()
                    ->state(fn (Driver $d): string => $d->approval_status === 'approved'
                        ? 'onayli'
                        : ($d->submitted_at ? 'bekliyor' : 'eksik'))
                    ->color(fn (string $state): string => match ($state) {
                        'bekliyor' => 'info',
                        'onayli'   => 'success',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bekliyor' => '🕒 İncelemede',
                        'onayli'   => 'Onaylı',
                        default    => 'Eksik/Devam',
                    })
                    ->tooltip('Sürücü tüm belgeleri yükleyip incelemeye gönderdi mi'),

                TextColumn::make('rating')
                    ->label('Puan')
                    ->formatStateUsing(fn ($state) => $state . ' ★')
                    ->color(fn ($state) => $state >= 4.5 ? 'success' : ($state >= 3.5 ? 'warning' : 'danger')),

                TextColumn::make('total_rides')
                    ->label('Toplam Yolculuk')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Komisyon')
                    ->suffix('%')
                    ->toggleable(),

                TextColumn::make('src_expires_at')
                    ->label('SRC Bitiş')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('availability_status')
                    ->label('Müsaitlik')
                    ->options([
                        'offline' => 'Çevrimdışı',
                        'online' => 'Müsait',
                        'busy' => 'Yolculukta',
                    ]),
                SelectFilter::make('approval_status')
                    ->label('Onay Durumu')
                    ->options([
                        'pending' => 'Beklemede',
                        'approved' => 'Onaylı',
                        'rejected' => 'Reddedildi',
                        'suspended' => 'Askıya',
                    ]),
                SelectFilter::make('city_id')
                    ->label('Şehir')
                    ->relationship('city', 'name'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('review_and_approve')
                    ->label('İncele & Onayla')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('success')
                    ->button()
                    ->visible(fn (Driver $d) => $d->approval_status !== 'approved')
                    ->modalHeading(fn (Driver $d) => 'Onboarding İnceleme: ' . ($d->user?->name ?? 'Sürücü'))
                    ->modalDescription('Yüklenen belge ve fotoğrafları incele. Araç sınıfını onayla/düzelt ve "Onayla" ile sürücüyü aktifleştir.')
                    ->modalSubmitActionLabel('✓ Onayla ve Aktifleştir')
                    ->schema([
                        Placeholder::make('review')
                            ->label('')
                            ->content(fn (Driver $d) => new HtmlString(self::buildOnboardingReviewHtml($d))),
                        Select::make('vehicle_class_id')
                            ->label('Onaylanan araç sınıfı (sürücünün önerisini düzeltebilirsin)')
                            ->options(fn () => VehicleClass::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                            ->default(fn (Driver $d) => $d->currentVehicle?->vehicle_class_id)
                            ->required(fn (Driver $d) => (bool) $d->currentVehicle),
                    ])
                    ->action(function (Driver $d, array $data) {
                        if (! $d->currentVehicle) {
                            Notification::make()->danger()->title('Araç bilgisi eksik')->body('Sürücü henüz araç bilgilerini tamamlamamış; onaylanamaz.')->send();
                            return;
                        }

                        $now = now();

                        // Araç: sınıfı onayla/düzelt + aktifleştir
                        $d->currentVehicle->update([
                            'vehicle_class_id'         => $data['vehicle_class_id'] ?? $d->currentVehicle->vehicle_class_id,
                            'class_confirmed_at'       => $now,
                            'status'                   => 'active',
                            'registration_approved_at' => $d->currentVehicle->registration_file_path ? $now : $d->currentVehicle->registration_approved_at,
                        ]);

                        // Yüklenmiş sürücü belgelerini onayla
                        $docCols = [
                            'license_file_path'         => 'license_approved_at',
                            'src_file_path'             => 'src_approved_at',
                            'psychotechnic_file_path'   => 'psychotechnic_approved_at',
                            'criminal_record_file_path' => 'criminal_record_approved_at',
                            'insurance_file_path'       => 'insurance_approved_at',
                            'inspection_file_path'      => 'inspection_approved_at',
                            'selfie_file_path'          => 'selfie_approved_at',
                        ];
                        $update = [
                            'approval_status' => 'approved',
                            'approved_at'     => $d->approved_at ?? $now,
                            'approved_by'     => Auth::id(),
                            'rejection_reason'=> null,
                        ];
                        foreach ($docCols as $fileCol => $approvedCol) {
                            if ($d->{$fileCol} && empty($d->{$approvedCol})) {
                                $update[$approvedCol] = $now;
                            }
                        }
                        $d->update($update);

                        Notification::make()
                            ->success()
                            ->title('✓ Sürücü onaylandı')
                            ->body('Araç sınıfı onaylandı, belgeler işaretlendi. Sürücü "Müsait" olduğunda radara düşer.')
                            ->persistent()
                            ->send();
                    }),

                Action::make('reject_application')
                    ->label('Reddet')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Driver $d) => ! in_array($d->approval_status, ['approved', 'rejected'], true))
                    ->modalHeading('Başvuruyu reddet')
                    ->schema([
                        Textarea::make('reason')->label('Red gerekçesi (sürücüye gösterilebilir)')->required()->rows(3),
                    ])
                    ->action(function (Driver $d, array $data) {
                        $d->update([
                            'approval_status'  => 'rejected',
                            'rejection_reason' => $data['reason'],
                        ]);
                        Notification::make()->warning()->title('Başvuru reddedildi')->send();
                    }),

                Action::make('reset_password_quick')
                    ->label('Yeni Şifre + SMS')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedKey)
                    ->color('warning')
                    ->button()
                    ->modalHeading('Yeni şifre oluştur ve sürücüye SMS gönder')
                    ->modalDescription('Rastgele üretilen şifre sürücünün telefonuna SMS ile iletilir. Bu ekranda bir kez gösterilir, sonra sistem şifreyi düz metin olarak tutmaz.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Yeni şifre')
                            ->required()
                            ->minLength(6)
                            ->default(fn () => Str::random(10))
                            ->helperText('Boş bırakma; SMS ile aynen bu değer gider.'),
                        Checkbox::make('send_sms')
                            ->label('SMS ile gönder (sürücünün kayıtlı cebine)')
                            ->default(true)
                            ->helperText('İşaretini kaldırırsan sadece şifre güncellenir, SMS gitmez.'),
                    ])
                    ->action(function (Driver $d, array $data) {
                        $user = $d->user;
                        if (! $user) {
                            Notification::make()->danger()->title('Bu sürücüye bağlı kullanıcı yok')->send();
                            return;
                        }

                        $user->update(['password' => Hash::make($data['password'])]);

                        $smsStatus = 'atlandi';
                        if (! empty($data['send_sms']) && $user->phone) {
                            try {
                                $phone   = preg_replace('/\s+/', '', $user->phone);
                                $message = "FerXGo sifren guncellendi. Giris: ferxgo.com/surucu-giris - "
                                         . "E-posta: {$user->email} - Yeni sifre: {$data['password']} - "
                                         . "Girip profil ekranindan degistir.";
                                $result  = app(VoiceTelekomClient::class)->sendSingle($phone, $message);
                                $smsStatus = ($result['ok'] ?? false) ? 'gonderildi' : 'basarisiz';
                                if (! ($result['ok'] ?? false)) {
                                    Log::warning('[PasswordReset] SMS gonderilemedi', [
                                        'driver_id' => $d->id,
                                        'phone'     => $phone,
                                        'error'     => $result['message'] ?? '?',
                                    ]);
                                }
                            } catch (\Throwable $e) {
                                Log::error('[PasswordReset] SMS exception: ' . $e->getMessage());
                                $smsStatus = 'basarisiz';
                            }
                        }

                        $smsBadge = match ($smsStatus) {
                            'gonderildi' => '✓ SMS gönderildi (' . $user->phone . ')',
                            'basarisiz'  => '⚠ SMS gönderilemedi — log\'a bak',
                            default      => 'SMS gönderilmedi',
                        };

                        Notification::make()
                            ->success()
                            ->title('✓ Şifre güncellendi')
                            ->body("E-posta: {$user->email}\nYeni şifre: {$data['password']}\n{$smsBadge}")
                            ->persistent()
                            ->send();
                    }),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('reset_password')
                        ->label('Şifre sıfırla + SMS gönder')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedKey)
                        ->color('warning')
                        ->modalHeading('Yeni şifre oluştur ve sürücüye SMS gönder')
                        ->modalDescription('Rastgele üretilen şifre sürücünün telefonuna SMS ile iletilir.')
                        ->schema([
                            TextInput::make('password')
                                ->label('Yeni şifre')
                                ->required()
                                ->minLength(6)
                                ->default(fn () => Str::random(10))
                                ->helperText('Boş bırakma; SMS ile aynen bu değer gider.'),
                            Checkbox::make('send_sms')
                                ->label('SMS ile gönder')
                                ->default(true)
                                ->helperText('İşaretini kaldırırsan sadece şifre güncellenir, SMS gitmez.'),
                        ])
                        ->action(function (Driver $d, array $data) {
                            $user = $d->user;
                            if (! $user) {
                                Notification::make()->danger()->title('Bu sürücüye bağlı kullanıcı yok')->send();
                                return;
                            }

                            $user->update(['password' => Hash::make($data['password'])]);

                            $smsStatus = 'atlandi';
                            if (! empty($data['send_sms']) && $user->phone) {
                                try {
                                    $phone   = preg_replace('/\s+/', '', $user->phone);
                                    $message = "FerXGo sifren guncellendi. Giris: ferxgo.com/surucu-giris - "
                                             . "E-posta: {$user->email} - Yeni sifre: {$data['password']} - "
                                             . "Girip profil ekranindan degistir.";
                                    $result  = app(VoiceTelekomClient::class)->sendSingle($phone, $message);
                                    $smsStatus = ($result['ok'] ?? false) ? 'gonderildi' : 'basarisiz';
                                    if (! ($result['ok'] ?? false)) {
                                        Log::warning('[PasswordReset] SMS gonderilemedi', [
                                            'driver_id' => $d->id,
                                            'phone'     => $phone,
                                            'error'     => $result['message'] ?? '?',
                                        ]);
                                    }
                                } catch (\Throwable $e) {
                                    Log::error('[PasswordReset] SMS exception: ' . $e->getMessage());
                                    $smsStatus = 'basarisiz';
                                }
                            }

                            $smsBadge = match ($smsStatus) {
                                'gonderildi' => '✓ SMS gönderildi',
                                'basarisiz'  => '⚠ SMS gönderilemedi',
                                default      => 'SMS gönderilmedi',
                            };

                            Notification::make()
                                ->success()
                                ->title('Şifre güncellendi')
                                ->body('E-posta: ' . $user->email . ' · Yeni şifre: ' . $data['password'] . ' · ' . $smsBadge)
                                ->persistent()
                                ->send();
                        }),
                    Action::make('diagnose')
                        ->label('🔍 Radarda görünmüyor? Tanı yap')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass)
                        ->color('warning')
                        ->modalHeading(fn (Driver $d) => 'Tanı: ' . ($d->user?->name ?? 'Sürücü'))
                        ->modalDescription('Bu sürücünün radarda/dispatch\'te görünmesi için 5 şartın tümü YEŞİL olmalı. Kırmızı olanları düzeltmek için modalda alttaki "Sorunları düzelt" butonuna bas.')
                        ->modalSubmitActionLabel('⚡ Sorunları otomatik düzelt')
                        ->schema([
                            Placeholder::make('diagnostic_report')
                                ->label('')
                                ->content(fn (Driver $d) => new HtmlString(self::buildDiagnosticHtml($d))),
                        ])
                        ->action(function (Driver $d) {
                            // Modal submit = Sorunları düzelt aksiyonu
                            $now = now();
                            $expires = $now->copy()->addDays(30);
                            $updates = [
                                'approval_status'          => 'approved',
                                'approved_at'              => $d->approved_at ?? $now,
                                'is_suspended'             => false,
                                'suspended_at'             => null,
                                'suspension_reason'        => null,
                                'availability_status'      => 'online',
                                'last_location_updated_at' => $now,
                            ];
                            if (! $d->package_active_until || $d->package_active_until->isPast()) {
                                DriverPackage::create([
                                    'driver_id'         => $d->id,
                                    'type'              => 'monthly',
                                    'duration_hours'    => 30 * 24,
                                    'price'             => 0.00,
                                    'starts_at'         => $now,
                                    'expires_at'        => $expires,
                                    'status'            => 'active',
                                    'payment_provider'  => 'manual_test',
                                    'payment_reference' => 'DIAG-' . $now->format('YmdHis'),
                                    'paid_at'           => $now,
                                ]);
                                $updates['package_active_until'] = $expires;
                            }
                            if (empty($d->current_lat) || empty($d->current_lng)) {
                                $updates['current_lat'] = 38.4192;
                                $updates['current_lng'] = 27.1287;
                            }
                            $d->update($updates);

                            $fresh = $d->fresh();
                            $ok = $fresh->approval_status === 'approved'
                                && $fresh->availability_status === 'online'
                                && ! $fresh->is_suspended
                                && $fresh->hasActivePackage()
                                && $fresh->current_lat && $fresh->current_lng;

                            Notification::make()
                                ->success()
                                ->title($ok ? '✅ Sürücü artık radarda görünür' : '⚠ Hâlâ bir sorun var, tanıyı tekrar aç')
                                ->body($ok
                                    ? 'Yolcu radar sayfasını (Yolculuk Yap) yenilesin, bu sürücü çıkar.'
                                    : 'Ekranı tekrar aç ve kırmızıları gör.')
                                ->persistent()
                                ->send();
                        }),
                    Action::make('force_ready_for_test')
                        ->label('⚡ Test için TAM HAZIRLA')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedBoltSlash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Sürücüyü test için tam hazırla')
                        ->modalDescription('Bu buton her şeyi yapar: onay + belgeler + askı kaldır + 30 günlük paket + çevrim içi. Sürücü anında radarda görünür ve talep alabilir.')
                        ->action(function (Driver $d) {
                            $now = now();
                            $expires = $now->copy()->addDays(30);

                            // 1) Test paketi
                            $pkg = DriverPackage::create([
                                'driver_id'         => $d->id,
                                'type'              => 'monthly',
                                'duration_hours'    => 30 * 24,
                                'price'             => 0.00,
                                'starts_at'         => $now,
                                'expires_at'        => $expires,
                                'status'            => 'active',
                                'payment_provider'  => 'manual_test',
                                'payment_reference' => 'TEST-' . $now->format('YmdHis'),
                                'paid_at'           => $now,
                            ]);

                            // 2) Belgeleri onayla (yüklenmiş olanları — yoksa yine de dispatch'e engel değil)
                            $docCols = [
                                'license_file_path'         => 'license_approved_at',
                                'src_file_path'             => 'src_approved_at',
                                'psychotechnic_file_path'   => 'psychotechnic_approved_at',
                                'criminal_record_file_path' => 'criminal_record_approved_at',
                                'insurance_file_path'       => 'insurance_approved_at',
                                'inspection_file_path'      => 'inspection_approved_at',
                            ];
                            $docUpdate = [];
                            foreach ($docCols as $fileCol => $approvedCol) {
                                if ($d->{$fileCol} && empty($d->{$approvedCol})) {
                                    $docUpdate[$approvedCol] = $now;
                                }
                            }

                            // 3) Ana driver güncellemesi — onay, paket cache, askı temizle, ONLINE yap
                            $d->update(array_merge($docUpdate, [
                                'approval_status'      => 'approved',
                                'approved_at'          => $d->approved_at ?? $now,
                                'is_suspended'         => false,
                                'suspended_at'         => null,
                                'suspension_reason'    => null,
                                'package_active_until' => $expires,
                                'availability_status'  => 'online',
                                'last_location_updated_at' => $now,
                            ]));

                            // 4) Konum boşsa İzmir Konak varsayılanı (radar için şart)
                            if (empty($d->current_lat) || empty($d->current_lng)) {
                                $d->update([
                                    'current_lat' => 38.4192,
                                    'current_lng' => 27.1287,
                                ]);
                            }

                            Notification::make()
                                ->success()
                                ->title('⚡ Sürücü test için TAM hazır')
                                ->body(
                                    'Paket #' . $pkg->id . ' · Bitiş: ' . $expires->format('d.m.Y') . "\n"
                                    . 'Onay: ✓ · Askı: ✗ · Belgeler: ' . count($docUpdate) . ' onay · Online: ✓' . "\n"
                                    . 'Konum: ' . $d->fresh()->current_lat . ', ' . $d->fresh()->current_lng
                                )
                                ->persistent()
                                ->send();
                        }),
                    Action::make('grant_test_package')
                        ->label('Test paketi ver (30 gün)')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedGift)
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Test paketi ver')
                        ->modalDescription('30 gün süreli ücretsiz test paketi oluşturulur. Sürücü hemen radar/dispatch havuzuna girer.')
                        ->action(function (Driver $d) {
                            $expires = now()->addDays(30);
                            $pkg = DriverPackage::create([
                                'driver_id'         => $d->id,
                                'type'              => 'monthly',
                                'duration_hours'    => 30 * 24,
                                'price'             => 0.00,
                                'starts_at'         => now(),
                                'expires_at'        => $expires,
                                'status'            => 'active',
                                'payment_provider'  => 'manual_test',
                                'payment_reference' => 'TEST-' . now()->format('YmdHis'),
                                'paid_at'           => now(),
                            ]);
                            $d->update(['package_active_until' => $expires]);
                            Notification::make()
                                ->success()
                                ->title('Test paketi verildi')
                                ->body('Paket #' . $pkg->id . ' · Bitiş: ' . $expires->format('d.m.Y'))
                                ->send();
                        }),
                    Action::make('approve_documents')
                        ->label('Tüm belgeleri onayla')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Yüklenmiş belgeleri onayla')
                        ->modalDescription('Bu sürücünün yüklediği tüm belgeler "onaylı" olarak işaretlenecek.')
                        ->action(function (Driver $d) {
                            $now = now();
                            $update = [];
                            $cols = [
                                'license_file_path'         => 'license_approved_at',
                                'src_file_path'             => 'src_approved_at',
                                'psychotechnic_file_path'   => 'psychotechnic_approved_at',
                                'criminal_record_file_path' => 'criminal_record_approved_at',
                                'insurance_file_path'       => 'insurance_approved_at',
                                'inspection_file_path'      => 'inspection_approved_at',
                            ];
                            foreach ($cols as $fileCol => $approvedCol) {
                                if ($d->{$fileCol} && empty($d->{$approvedCol})) {
                                    $update[$approvedCol] = $now;
                                }
                            }
                            if (! empty($update)) {
                                $d->update($update);
                                Notification::make()->success()->title(count($update) . ' belge onaylandı')->send();
                            } else {
                                Notification::make()->info()->title('Onaylanacak belge yok')->send();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Sürücünün radar/dispatch şartlarının HTML tanı raporu.
     * 5 şart var — tümü YEŞİL olmalı ki sürücü radarda görünsün.
     */
    private static function buildDiagnosticHtml(Driver $d): string
    {
        $d = $d->loadMissing('user');

        $checks = [
            [
                'ok'    => $d->approval_status === 'approved',
                'label' => 'Onay durumu',
                'okMsg' => 'Onaylı ✓',
                'badMsg'=> 'Durum: ' . $d->approval_status . ' — Sürücü onaylanmalı.',
            ],
            [
                'ok'    => $d->availability_status === 'online',
                'label' => 'Müsaitlik',
                'okMsg' => 'Çevrim içi (Müsait) ✓',
                'badMsg'=> 'Durum: ' . $d->availability_status . ' — Sürücü panelinde "Müsait" yapmalı VEYA burada online\'a çekmeli.',
            ],
            [
                'ok'    => ! $d->is_suspended,
                'label' => 'Askı durumu',
                'okMsg' => 'Askıda değil ✓',
                'badMsg'=> 'Sürücü askıda: ' . ($d->suspension_reason ?: 'sebep yok'),
            ],
            [
                'ok'    => $d->hasActivePackage(),
                'label' => 'Aktif paket',
                'okMsg' => 'Paket geçerli · Bitiş: ' . ($d->package_active_until?->format('d.m.Y H:i') ?? '—'),
                'badMsg'=> $d->package_active_until
                    ? 'Paket süresi dolmuş: ' . $d->package_active_until->format('d.m.Y H:i')
                    : 'Paket yok — sürücü paket almalı veya admin panel test paketi versin.',
            ],
            [
                'ok'    => ! empty($d->current_lat) && ! empty($d->current_lng),
                'label' => 'GPS Konumu',
                'okMsg' => 'Konum: ' . $d->current_lat . ', ' . $d->current_lng
                    . ' (' . ($d->last_location_updated_at?->diffForHumans() ?? '—') . ')',
                'badMsg'=> 'Konum yok — sürücü panelde tarayıcıya konum izni vermeli. Ya da admin default İzmir konumu atayabilir.',
            ],
        ];

        $html = '<div style="font-family: system-ui,sans-serif; font-size: 14px; line-height: 1.6;">';
        $html .= '<div style="margin-bottom: 12px; padding: 10px 14px; background: rgba(59,130,246,0.08); border-left: 3px solid #3b82f6; border-radius: 6px;">';
        $html .= '<strong>Sürücü:</strong> ' . e($d->user?->name ?? 'Bilinmiyor')
              . ' · <strong>Telefon:</strong> ' . e($d->user?->phone ?? '—')
              . ' · <strong>ID:</strong> #' . $d->id;
        $html .= '</div>';

        $allOk = true;
        foreach ($checks as $c) {
            if (! $c['ok']) $allOk = false;
            $icon    = $c['ok'] ? '✅' : '❌';
            $color   = $c['ok'] ? '#10b981' : '#ef4444';
            $bg      = $c['ok'] ? 'rgba(16,185,129,0.08)' : 'rgba(239,68,68,0.08)';
            $message = $c['ok'] ? $c['okMsg'] : $c['badMsg'];
            $html .= '<div style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; margin-bottom: 6px; background: ' . $bg . '; border-left: 3px solid ' . $color . '; border-radius: 6px;">';
            $html .= '<div style="font-size: 18px;">' . $icon . '</div>';
            $html .= '<div style="flex: 1;">';
            $html .= '<div style="font-weight: 600; color: ' . $color . ';">' . e($c['label']) . '</div>';
            $html .= '<div style="color: #6b7280; font-size: 13px;">' . e($message) . '</div>';
            $html .= '</div></div>';
        }

        $html .= '<div style="margin-top: 16px; padding: 12px 14px; background: ' . ($allOk ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)') . '; border-radius: 8px; text-align: center; font-weight: 600;">';
        $html .= $allOk
            ? '🎉 Tüm şartlar tamam — sürücü radarda görünüyor. Radar yenilenmediyse yolcu tarafında sayfayı F5 yapın.'
            : '⚠ Yukarıdaki kırmızıları düzeltmek için modalın altındaki turuncu "Sorunları otomatik düzelt" butonuna bas.';
        $html .= '</div>';

        // Ek not: kadın yolcu filtresi
        if ($d->women_passengers_only) {
            $html .= '<div style="margin-top: 10px; padding: 10px 14px; background: rgba(236,72,153,0.08); border-left: 3px solid #ec4899; border-radius: 6px; font-size: 13px;">';
            $html .= '💡 <strong>Bilgi:</strong> Bu sürücüde "Sadece kadın yolcu al" açık. Yalnızca cinsiyet: kadın olan müşteriler bu sürücüyü Hızlı Seç ekranında görebilir. Radar sayfasında herkese görünür.';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Onboarding inceleme raporu: araç bilgisi + yüklenen belge/fotoğraf linkleri
     * + tamamlanma durumu. Admin buradan her belgeyi tıklayıp görür.
     */
    private static function buildOnboardingReviewHtml(Driver $d): string
    {
        $d = $d->loadMissing('user', 'currentVehicle.vehicleClass', 'currentVehicle.vehicleMake', 'currentVehicle.vehicleModel');
        $v = $d->currentVehicle;
        $ob = app(DriverOnboardingService::class)->status($d);

        $link = function (?string $path, string $label): string {
            if (! $path) return '<span style="color:#ef4444;">✗ ' . e($label) . ' — yok</span>';
            $url = str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
            return '✅ <a href="' . e($url) . '" target="_blank" style="color:#3b82f6; text-decoration:underline;">' . e($label) . '</a>';
        };

        $html = '<div style="font-family: system-ui,sans-serif; font-size: 13px; line-height: 1.7;">';

        // Tamamlanma
        $barColor = $ob['is_ready_for_review'] ? '#10b981' : '#f59e0b';
        $html .= '<div style="margin-bottom:12px; padding:10px 14px; background:rgba(0,0,0,0.04); border-radius:8px;">';
        $html .= '<strong>Tamamlanma:</strong> ' . $ob['completed'] . '/' . $ob['total'] . ' (%' . $ob['percent'] . ') · '
              . '<span style="color:' . $barColor . '; font-weight:600;">' . ($ob['is_ready_for_review'] ? 'İncelemeye hazır' : 'Eksik var') . '</span>';
        if (! empty($ob['missing'])) {
            $html .= '<div style="color:#b45309; font-size:12px; margin-top:4px;">Eksik: ' . e(implode(', ', $ob['missing'])) . '</div>';
        }
        $html .= '</div>';

        // Araç
        $html .= '<div style="margin-bottom:8px; font-weight:700;">🚗 Araç</div>';
        if ($v) {
            $html .= '<div style="margin-bottom:10px; color:#374151;">'
                . e($v->vehicle_type ?: '—') . ' · ' . e(($v->vehicleMake?->name ?? $v->brand) . ' ' . ($v->vehicleModel?->name ?? $v->model))
                . ' · ' . e((string) $v->year_of_manufacture) . ' · ' . e((string) $v->color)
                . ' · Plaka: <strong>' . e((string) $v->plate) . '</strong>'
                . ' · Önerilen sınıf: <strong>' . e($v->vehicleClass?->name ?? '—') . '</strong>'
                . '</div>';
            // Fotoğraflar
            $angles = ['left'=>'Sol','front'=>'Ön','right'=>'Sağ','back'=>'Arka','interior_front'=>'İç ön','interior_back'=>'İç arka'];
            $pa = is_array($v->photo_angles) ? $v->photo_angles : [];
            $html .= '<div style="margin-bottom:10px;">';
            foreach ($angles as $k => $lbl) {
                $html .= '<div>' . $link($pa[$k] ?? null, 'Foto: ' . $lbl) . '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div style="color:#ef4444; margin-bottom:10px;">Araç bilgisi henüz girilmemiş.</div>';
        }

        // Belgeler
        $html .= '<div style="margin-bottom:8px; font-weight:700;">📄 Belgeler</div>';
        $html .= '<div>' . $link($d->license_file_path, 'Ehliyet') . '</div>';
        $html .= '<div>' . $link($d->selfie_file_path, 'Selfie') . '</div>';
        $html .= '<div>' . $link($d->src_file_path, 'SRC') . '</div>';
        $html .= '<div>' . $link($d->criminal_record_file_path, 'Adli Sicil') . '</div>';
        $html .= '<div>' . $link($d->psychotechnic_file_path, 'Psikoteknik') . '</div>';
        $html .= '<div>' . $link($v?->registration_file_path, 'Ruhsat') . '</div>';
        $html .= '<div>' . $link($d->insurance_file_path, 'Sigorta') . '</div>';
        $html .= '<div>' . $link($d->inspection_file_path, 'Muayene') . '</div>';

        $html .= '</div>';

        return $html;
    }
}
