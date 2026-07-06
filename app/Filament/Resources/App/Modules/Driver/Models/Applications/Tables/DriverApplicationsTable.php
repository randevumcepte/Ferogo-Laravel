<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Applications\Tables;

use App\Models\User;
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

                TextColumn::make('gender')
                    ->label('Cinsiyet')
                    ->badge()
                    ->color(fn (?string $s): string => match ($s) {
                        'female' => 'danger',
                        'male'   => 'info',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
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
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        'under_1' => '<1 yıl',
                        '1_to_3'  => '1-3 yıl',
                        '3_to_5'  => '3-5 yıl',
                        '5_plus'  => '5+ yıl',
                        default   => $s ?? '—',
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
                    ->color(fn (string $s): string => match ($s) {
                        'pending'   => 'warning',
                        'contacted' => 'info',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'pending'   => 'Beklemede',
                        'contacted' => 'İletişime geçildi',
                        'approved'  => 'Onaylandı',
                        'rejected'  => 'Reddedildi',
                        default     => $s,
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
                    ])
                    ->default('pending'),
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
                    ->visible(fn (DriverApplication $a) => $a->status !== 'approved')
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

                        $genderNote = $a->gender === 'female' ? ' 👩 Kadın sürücü' : ' 👨 Erkek sürücü';
                        Notification::make()
                            ->success()
                            ->title('Sürücü onaylandı' . $genderNote)
                            ->body('Giriş: ' . $data['email'] . ' · Şifre: ' . $data['password'])
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
