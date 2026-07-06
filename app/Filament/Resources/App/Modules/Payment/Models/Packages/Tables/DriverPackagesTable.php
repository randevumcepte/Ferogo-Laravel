<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Packages\Tables;

use App\Modules\Payment\Models\DriverPackage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriverPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('driver.user.name')
                    ->label('Sürücü')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Paket')
                    ->badge(),

                TextColumn::make('duration_hours')
                    ->label('Süre')
                    ->suffix(' saat'),

                TextColumn::make('price')
                    ->label('Ücret')
                    ->money('TRY', locale: 'tr'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'active'   => 'success',
                        'expired'  => 'gray',
                        'pending'  => 'warning',
                        'failed'   => 'danger',
                        'refunded' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'active'   => 'Aktif',
                        'expired'  => 'Süresi doldu',
                        'pending'  => 'Bekliyor',
                        'failed'   => 'Başarısız',
                        'refunded' => 'İade',
                        default    => $s,
                    }),

                TextColumn::make('starts_at')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Bitiş')
                    ->dateTime('d.m.Y H:i')
                    ->color(fn ($state) => $state && $state->isPast() ? 'gray' : 'success'),

                TextColumn::make('paid_at')
                    ->label('Ödendi')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_reference')
                    ->label('Referans')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Talep')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'active'   => 'Aktif',
                        'expired'  => 'Süresi doldu',
                        'pending'  => 'Bekliyor',
                        'failed'   => 'Başarısız',
                        'refunded' => 'İade',
                    ]),
                SelectFilter::make('type')
                    ->label('Paket türü')
                    ->options(fn () => DriverPackage::query()
                        ->distinct()
                        ->pluck('type', 'type')
                        ->all()),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(null);
    }
}
