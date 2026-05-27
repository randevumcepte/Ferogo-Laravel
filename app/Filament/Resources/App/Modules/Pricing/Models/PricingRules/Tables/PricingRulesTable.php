<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PricingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('city.name')
                    ->label('Şehir')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('vehicleClass.name')
                    ->label('Sınıf')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'VIP' => 'warning',
                        'Platinum' => 'info',
                        'Easy' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('base_fare')
                    ->label('Açılış')
                    ->money('TRY'),

                TextColumn::make('per_km_fare')
                    ->label('Km')
                    ->money('TRY'),

                TextColumn::make('per_minute_fare')
                    ->label('Dakika')
                    ->money('TRY'),

                TextColumn::make('minimum_fare')
                    ->label('Min.')
                    ->money('TRY'),

                TextColumn::make('night_multiplier')
                    ->label('Gece')
                    ->suffix('×'),

                TextColumn::make('peak_multiplier')
                    ->label('Yoğun')
                    ->suffix('×'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('city_id')
                    ->label('Şehir')
                    ->relationship('city', 'name'),
                SelectFilter::make('vehicle_class_id')
                    ->label('Sınıf')
                    ->relationship('vehicleClass', 'name'),
            ])
            ->defaultSort('city_id')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
