<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LegalTextVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->width('70px'),

                TextColumn::make('key')
                    ->label('Metin')
                    ->badge()
                    ->searchable(),

                TextColumn::make('version')
                    ->label('Versiyon')
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('title')
                    ->label('Başlık')
                    ->limit(40)
                    ->color('gray'),

                TextColumn::make('sha256')
                    ->label('SHA-256')
                    ->limit(12)
                    ->tooltip(fn ($state) => $state)
                    ->color('gray')
                    ->copyable(),

                TextColumn::make('published_at')
                    ->label('Yayın')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('superseded_at')
                    ->label('Pasif')
                    ->date('d.m.Y')
                    ->placeholder('— aktif —')
                    ->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'success'),

                TextColumn::make('consents_count')
                    ->label('Onay Sayısı')
                    ->counts('consents')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('key')
                    ->label('Metin')
                    ->options([
                        'platform_notice'     => 'Platform Bildirimi',
                        'terms'               => 'Hizmet Şartları',
                        'kvkk'                => 'KVKK',
                        'distance_sales'      => 'Mesafeli Satış',
                        'cookies'             => 'Çerez',
                        'ride_sharing'        => 'Paylaşımlı Yolculuk',
                        'driver_registration' => 'Sürücü Kayıt',
                        'reservation_kvkk'    => 'Rezervasyon KVKK',
                    ]),
                TernaryFilter::make('superseded_at')
                    ->label('Aktif mi?')
                    ->placeholder('Hepsi')
                    ->trueLabel('Aktif')
                    ->falseLabel('Pasif')
                    ->queries(
                        true: fn ($q) => $q->whereNull('superseded_at'),
                        false: fn ($q) => $q->whereNotNull('superseded_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->striped();
    }
}
