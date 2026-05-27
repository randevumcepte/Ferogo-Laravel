<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plate')
                    ->label('Plaka')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('vehicleClass.name')
                    ->label('Sınıf')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'VIP' => 'warning',
                        'Platinum' => 'info',
                        'Easy' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('brand')
                    ->label('Marka/Model')
                    ->formatStateUsing(fn ($state, $record) => $record->brand . ' ' . $record->model)
                    ->searchable(['brand', 'model']),

                TextColumn::make('year_of_manufacture')
                    ->label('Yıl')
                    ->alignCenter(),

                TextColumn::make('color')
                    ->label('Renk'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'retired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'pending' => 'Beklemede',
                        'suspended' => 'Askıda',
                        'retired' => 'Hizmet Dışı',
                        default => $state,
                    }),

                TextColumn::make('insurance_expires_at')
                    ->label('Sigorta')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->toggleable(),

                TextColumn::make('inspection_expires_at')
                    ->label('Muayene')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vehicle_class_id')
                    ->label('Sınıf')
                    ->relationship('vehicleClass', 'name'),
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending' => 'Beklemede',
                        'active' => 'Aktif',
                        'suspended' => 'Askıda',
                        'retired' => 'Hizmet Dışı',
                    ]),
            ])
            ->defaultSort('id', 'desc')
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
