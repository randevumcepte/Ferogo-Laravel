<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\RideRequests\Tables;

use App\Modules\Booking\Models\RideRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RideRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('customer_name')
                    ->label('Yolcu')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('customer_phone')
                    ->label('Telefon')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('pickup_address')
                    ->label('Rota')
                    ->formatStateUsing(fn ($state, $record) =>
                        \Illuminate\Support\Str::limit($state, 40) . ' → ' .
                        \Illuminate\Support\Str::limit($record->dropoff_address ?? '', 40)
                    )
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 340px;']),

                TextColumn::make('distance_km')
                    ->label('km')
                    ->numeric(1),

                TextColumn::make('estimated_fare')
                    ->label('Tahmini')
                    ->money('TRY', locale: 'tr')
                    ->placeholder('—'),

                TextColumn::make('acceptedDriver.user.name')
                    ->label('Kabul eden')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'accepted'  => 'success',
                        'rejected'  => 'gray',
                        'expired'   => 'danger',
                        'cancelled' => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Bekliyor',
                        'accepted'  => 'Kabul edildi',
                        'rejected'  => 'Reddedildi',
                        'expired'   => 'Süresi doldu',
                        'cancelled' => 'İptal',
                        default     => $state,
                    }),

                TextColumn::make('offer_expires_at')
                    ->label('Teklif biter')
                    ->dateTime('H:i:s')
                    ->color(fn ($state) => $state && $state->isFuture() ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Ne zaman')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending'   => 'Bekliyor',
                        'accepted'  => 'Kabul edildi',
                        'rejected'  => 'Reddedildi',
                        'expired'   => 'Süresi doldu',
                        'cancelled' => 'İptal',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->poll('30s');
    }
}
