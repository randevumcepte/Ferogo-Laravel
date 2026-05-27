<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\Rides\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('customer_name')
                    ->label('Müşteri')
                    ->searchable(['customer_name', 'customer_phone'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->customer_phone),

                TextColumn::make('vehicleClass.name')
                    ->label('Sınıf')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'VIP' => 'warning',
                        'Platinum' => 'info',
                        'Easy' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('pickup_address')
                    ->label('Alış')
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('dropoff_address')
                    ->label('Bırakış')
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('driver.user.name')
                    ->label('Sürücü')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending', 'searching' => 'warning',
                        'assigned', 'driver_arriving' => 'info',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'cancelled', 'no_show' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Taslak',
                        'pending' => 'Beklemede',
                        'searching' => 'Sürücü Aranıyor',
                        'assigned' => 'Atandı',
                        'driver_arriving' => 'Sürücü Yolda',
                        'in_progress' => 'Yolculukta',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                        'no_show' => 'Gelmedi',
                        default => $state,
                    }),

                TextColumn::make('total_fare')
                    ->label('Tutar')
                    ->money('TRY')
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Kaynak')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'web' => 'Web',
                        'app' => 'App',
                        'call' => 'Telefon',
                        'whatsapp' => 'WhatsApp',
                        default => $state,
                    }),

                TextColumn::make('scheduled_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->multiple()
                    ->options([
                        'pending' => 'Beklemede',
                        'searching' => 'Sürücü Aranıyor',
                        'assigned' => 'Atandı',
                        'driver_arriving' => 'Sürücü Yolda',
                        'in_progress' => 'Yolculukta',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                        'no_show' => 'Gelmedi',
                    ]),
                SelectFilter::make('city_id')
                    ->label('Şehir')
                    ->relationship('city', 'name'),
                SelectFilter::make('vehicle_class_id')
                    ->label('Sınıf')
                    ->relationship('vehicleClass', 'name'),
                SelectFilter::make('source')
                    ->label('Kaynak')
                    ->options([
                        'web' => 'Web',
                        'app' => 'App',
                        'call' => 'Telefon',
                        'whatsapp' => 'WhatsApp',
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
