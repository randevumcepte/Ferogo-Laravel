<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Applications\Tables;

use App\Models\User;
use App\Modules\Booking\Services\Sms\VoiceTelekomClient;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
                        DB::transaction(function () use ($a, $data) {
                            $user = User::create([
                                'name'     => $a->full_name,
                                'email'    => $data['email'],
                                'password' => Hash::make($data['password']),
                                'phone'    => preg_replace('/\s+/', '', $a->phone),
                                'gender'   => $a->gender,
                                'type'     => 'driver',
                                'status'   => 'active',
                            ]);

                            Driver::create([
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
                            ]);

                            $a->update(['status' => 'approved']);
                        });

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
}
