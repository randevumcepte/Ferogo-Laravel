<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable()->width('60px'),

                TextColumn::make('name')
                    ->label('Sınıf')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'VIP' => 'warning',
                        'Platinum' => 'info',
                        'Easy' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('max_passengers')
                    ->label('Yolcu')
                    ->alignCenter()
                    ->icon('heroicon-o-user'),

                TextColumn::make('max_luggage')
                    ->label('Bagaj')
                    ->alignCenter()
                    ->icon('heroicon-o-shopping-bag'),

                TextColumn::make('base_fare')
                    ->label('Açılış')
                    ->money('TRY')
                    ->sortable(),

                TextColumn::make('per_km_fare')
                    ->label('Km')
                    ->money('TRY'),

                TextColumn::make('per_minute_fare')
                    ->label('Dakika')
                    ->money('TRY'),

                TextColumn::make('minimum_fare')
                    ->label('Min. Ücret')
                    ->money('TRY'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
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
