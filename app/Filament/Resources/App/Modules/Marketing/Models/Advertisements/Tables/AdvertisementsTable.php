<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Tables;

use App\Modules\Marketing\Models\Advertisement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AdvertisementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable()->width('60px'),

                TextColumn::make('placement')
                    ->label('Alan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ride_tracking' => 'warning',
                        'home_banner' => 'success',
                        'radar_map' => 'info',
                        'driver_panel' => 'gray',
                        'sponsored_notification' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Advertisement::PLACEMENTS[$state] ?? $state),

                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Advertisement $record): ?string => $record->sponsor_name),

                TextColumn::make('sector')
                    ->label('Sektör')
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Advertisement::SECTORS[$state] ?? $state) : '—'),

                TextColumn::make('mode')
                    ->label('Tür')
                    ->badge()
                    ->state(fn (Advertisement $r): string => $r->is_exclusive ? 'Tekel' : 'Rotasyon ×' . max(1, (int) $r->rotation_weight))
                    ->color(fn (Advertisement $r): string => $r->is_exclusive ? 'warning' : 'gray'),

                TextColumn::make('impressions')
                    ->label('Gösterim')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('clicks')
                    ->label('Tıklama')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('placement')
                    ->label('Reklam Alanı')
                    ->options(Advertisement::PLACEMENTS),

                TernaryFilter::make('is_active')
                    ->label('Aktiflik'),
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
