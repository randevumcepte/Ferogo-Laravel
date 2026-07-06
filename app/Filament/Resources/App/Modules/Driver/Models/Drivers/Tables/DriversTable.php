<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Tables;

use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Models\DriverPackage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
}
