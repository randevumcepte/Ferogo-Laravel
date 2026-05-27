<?php

namespace App\Filament\Resources\App\Modules\Shared\Models\Cities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable()->width('60px'),

                TextColumn::make('name')
                    ->label('Şehir')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray'),

                TextColumn::make('country_code')
                    ->label('Ülke')
                    ->badge(),

                TextColumn::make('center_lat')
                    ->label('Enlem')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('center_lng')
                    ->label('Boylam')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktif Durumu')
                    ->placeholder('Tümü')
                    ->trueLabel('Sadece aktif')
                    ->falseLabel('Sadece pasif'),
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
