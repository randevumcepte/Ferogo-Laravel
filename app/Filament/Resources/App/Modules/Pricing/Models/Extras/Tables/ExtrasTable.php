<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\Extras\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExtrasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable()->width('60px'),

                TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray'),

                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'seat' => 'info',
                        'pet' => 'warning',
                        'package' => 'success',
                        'baggage' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'seat' => 'Koltuk',
                        'pet' => 'Evcil Hayvan',
                        'package' => 'Paket',
                        'baggage' => 'Bagaj',
                        'other' => 'Diğer',
                        default => $state,
                    }),

                TextColumn::make('price')
                    ->label('Fiyat')
                    ->money('TRY')
                    ->sortable(),

                IconColumn::make('per_unit')
                    ->label('Adet × Fiyat')
                    ->boolean(),

                TextColumn::make('max_quantity')
                    ->label('Maks.')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options([
                        'seat' => 'Koltuk',
                        'pet' => 'Evcil Hayvan',
                        'package' => 'Paket',
                        'baggage' => 'Bagaj',
                        'other' => 'Diğer',
                    ]),
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
