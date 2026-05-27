<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('user.name')
                    ->label('Sürücü')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.phone')
                    ->label('Telefon')
                    ->copyable(),

                TextColumn::make('city.name')
                    ->label('Şehir')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('availability_status')
                    ->label('Müsaitlik')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'busy' => 'warning',
                        'offline' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'Müsait',
                        'busy' => 'Yolculukta',
                        'offline' => 'Çevrimdışı',
                        default => $state,
                    }),

                TextColumn::make('approval_status')
                    ->label('Onay')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Onaylı',
                        'pending' => 'Beklemede',
                        'rejected' => 'Reddedildi',
                        'suspended' => 'Askıya',
                        default => $state,
                    }),

                TextColumn::make('rating')
                    ->label('Puan')
                    ->formatStateUsing(fn ($state) => $state . ' ★')
                    ->color(fn ($state) => $state >= 4.5 ? 'success' : ($state >= 3.5 ? 'warning' : 'danger')),

                TextColumn::make('total_rides')
                    ->label('Toplam Yolculuk')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Komisyon')
                    ->suffix('%')
                    ->toggleable(),

                TextColumn::make('src_expires_at')
                    ->label('SRC Bitiş')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('availability_status')
                    ->label('Müsaitlik')
                    ->options([
                        'offline' => 'Çevrimdışı',
                        'online' => 'Müsait',
                        'busy' => 'Yolculukta',
                    ]),
                SelectFilter::make('approval_status')
                    ->label('Onay Durumu')
                    ->options([
                        'pending' => 'Beklemede',
                        'approved' => 'Onaylı',
                        'rejected' => 'Reddedildi',
                        'suspended' => 'Askıya',
                    ]),
                SelectFilter::make('city_id')
                    ->label('Şehir')
                    ->relationship('city', 'name'),
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
