<?php

namespace App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PanicAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tetiklenme')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('triggered_by_type')
                    ->label('Kim')
                    ->badge()
                    ->color(fn (string $state) => $state === 'driver' ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === 'driver' ? 'Üye Sürücü' : 'Yolcu'),

                TextColumn::make('person_name')
                    ->label('Ad Soyad')
                    ->weight('bold')
                    ->state(function ($record) {
                        $name = $record->triggered_by_type === 'driver'
                            ? ($record->driver?->user?->name ?? $record->triggeredByUser?->name)
                            : ($record->rideRequest?->customer_name ?? $record->triggeredByUser?->name);

                        return $name ?: '—';
                    }),

                TextColumn::make('triggered_by_phone')
                    ->label('Telefon')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'triggered'         => 'danger',
                        'acknowledged'      => 'warning',
                        'contacting'        => 'warning',
                        'police_dispatched' => 'danger',
                        'resolved'          => 'success',
                        'false_alarm'       => 'gray',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'triggered'         => '🚨 YENİ ALARM',
                        'acknowledged'      => 'Görüldü',
                        'contacting'        => 'Aranıyor',
                        'police_dispatched' => 'Polis Çağrıldı',
                        'resolved'          => 'Çözüldü',
                        'false_alarm'       => 'Yanlış Alarm',
                        default             => $state,
                    }),

                TextColumn::make('lat')
                    ->label('Konum')
                    ->formatStateUsing(fn ($state, $record) =>
                        $state ? sprintf('📍 %.5f, %.5f', $state, $record->lng) : '—'
                    )
                    ->url(fn ($record) => $record->lat
                        ? sprintf('https://www.google.com/maps?q=%f,%f', $record->lat, $record->lng)
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('ride_request_id')
                    ->label('Yolculuk')
                    ->formatStateUsing(fn ($state) => $state ? '#' . $state : '—'),

                TextColumn::make('handler.name')
                    ->label('Operatör')
                    ->placeholder('—'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['driver.user', 'rideRequest', 'triggeredByUser']))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'triggered'         => '🚨 Yeni Alarm',
                        'acknowledged'      => 'Görüldü',
                        'contacting'        => 'Aranıyor',
                        'police_dispatched' => 'Polis',
                        'resolved'          => 'Çözüldü',
                        'false_alarm'       => 'Yanlış Alarm',
                    ]),
                SelectFilter::make('triggered_by_type')
                    ->label('Tarafı')
                    ->options(['driver' => 'Üye Sürücü', 'customer' => 'Yolcu']),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->striped();
    }
}
