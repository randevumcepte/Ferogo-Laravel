<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('ride.id')
                    ->label('Rezervasyon')
                    ->prefix('#'),

                TextColumn::make('user.name')
                    ->label('Müşteri')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Tutar')
                    ->money('TRY')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('provider')
                    ->label('Sağlayıcı')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'iyzico' => 'iyzico',
                        'cash' => 'Nakit',
                        'card_on_arrival' => 'Araçta Kart',
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'captured' => 'success',
                        'authorized' => 'info',
                        'pending' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Beklemede',
                        'authorized' => 'Yetkili',
                        'captured' => 'Tahsil Edildi',
                        'failed' => 'Başarısız',
                        'refunded' => 'İade',
                        'cancelled' => 'İptal',
                        default => $state,
                    }),

                TextColumn::make('card_last_4')
                    ->label('Kart')
                    ->prefix('**** ')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending' => 'Beklemede',
                        'authorized' => 'Yetkili',
                        'captured' => 'Tahsil Edildi',
                        'failed' => 'Başarısız',
                        'refunded' => 'İade',
                        'cancelled' => 'İptal',
                    ]),
                SelectFilter::make('provider')
                    ->label('Sağlayıcı')
                    ->options([
                        'iyzico' => 'iyzico',
                        'cash' => 'Nakit',
                        'card_on_arrival' => 'Araçta Kart',
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
