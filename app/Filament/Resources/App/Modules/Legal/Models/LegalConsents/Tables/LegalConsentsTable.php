<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LegalConsentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->width('70px'),

                TextColumn::make('accepted_at')
                    ->label('Kabul Tarihi')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                TextColumn::make('consent_type')
                    ->label('Onay Türü')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'platform_notice'      => 'success',
                        'driver_registration'  => 'warning',
                        'reservation_kvkk'     => 'info',
                        default                => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('version_snapshot')
                    ->label('Versiyon')
                    ->color('gray')
                    ->copyable(),

                TextColumn::make('accepted_via')
                    ->label('Kanal')
                    ->badge(),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('— anonim —')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->color('gray')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('sha256_snapshot')
                    ->label('SHA-256')
                    ->color('gray')
                    ->limit(12)
                    ->tooltip(fn ($state) => $state)
                    ->copyable(),

                TextColumn::make('user.name')
                    ->label('Kullanıcı')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->defaultSort('accepted_at', 'desc')
            ->filters([
                SelectFilter::make('consent_type')
                    ->label('Onay Türü')
                    ->options([
                        'platform_notice'     => 'Platform Bildirimi',
                        'terms'               => 'Hizmet Şartları',
                        'kvkk'                => 'KVKK',
                        'distance_sales'      => 'Mesafeli Satış',
                        'cookies'             => 'Çerez',
                        'ride_sharing'        => 'Paylaşımlı Yolculuk',
                        'driver_registration' => 'Sürücü Kayıt',
                        'reservation_kvkk'    => 'Rezervasyon KVKK',
                    ]),
                SelectFilter::make('accepted_via')
                    ->label('Kanal')
                    ->options([
                        'modal'                => 'Modal',
                        'checkbox'             => 'Checkbox',
                        'driver_registration'  => 'Sürücü Kayıt',
                        'reservation'          => 'Rezervasyon',
                        'sms_otp'              => 'SMS OTP',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->striped();
    }
}
