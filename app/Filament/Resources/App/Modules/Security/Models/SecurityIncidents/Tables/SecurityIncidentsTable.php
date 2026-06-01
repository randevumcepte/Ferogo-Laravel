<?php

namespace App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SecurityIncidentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'visual_mismatch' => 'danger',
                        'wrong_vehicle'   => 'danger',
                        'wrong_driver'    => 'danger',
                        'panic_button'    => 'danger',
                        'safety_concern'  => 'warning',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'visual_mismatch'   => '👁 Görsel Uyumsuz',
                        'wrong_vehicle'     => '🚗 Yanlış Araç',
                        'wrong_driver'      => '🙅 Yanlış Sürücü',
                        'driver_no_show'    => '⏰ Sürücü Gelmedi',
                        'customer_no_show'  => '⏰ Yolcu Gelmedi',
                        'safety_concern'    => '⚠ Güvenlik',
                        'panic_button'      => '🚨 Panik Butonu',
                        'other'             => 'Diğer',
                        default             => $state,
                    }),

                TextColumn::make('reported_by')
                    ->label('İhbar')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'customer' => 'Yolcu',
                        'driver'   => 'Sürücü',
                        'system'   => 'Sistem',
                        'operator' => 'Operatör',
                        default    => $state,
                    }),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open'                => 'danger',
                        'investigating'       => 'warning',
                        'resolved_ok'         => 'success',
                        'resolved_suspended'  => 'gray',
                        'escalated_police'    => 'danger',
                        default               => 'gray',
                    }),

                TextColumn::make('driver.user.name')
                    ->label('Sürücü')
                    ->placeholder('—'),

                TextColumn::make('verificationPhotos_count')
                    ->label('Foto')
                    ->counts('verificationPhotos')
                    ->formatStateUsing(fn ($state) => "{$state} / 3"),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open'                => 'Açık',
                        'investigating'       => 'İnceleniyor',
                        'resolved_ok'         => 'Çözüldü (OK)',
                        'resolved_suspended'  => 'Sürücü Askıya Alındı',
                        'escalated_police'    => 'Polise Yönlendirildi',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'visual_mismatch'   => 'Görsel Uyumsuz',
                        'wrong_vehicle'     => 'Yanlış Araç',
                        'wrong_driver'      => 'Yanlış Sürücü',
                        'safety_concern'    => 'Güvenlik Endişesi',
                        'panic_button'      => 'Panik Butonu',
                    ]),
            ])
            ->recordActions([ViewAction::make()])
            ->striped();
    }
}
