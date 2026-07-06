<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\NoShowReports\Tables;

use App\Modules\Booking\Models\NoShowReport;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NoShowReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('driver.user.name')
                    ->label('Sürücü')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('customer_phone')
                    ->label('Yolcu Telefon')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('wait_seconds')
                    ->label('Bekleme')
                    ->formatStateUsing(fn ($state) => $state ? gmdate('i:s', (int) $state) : '—'),

                TextColumn::make('distance_from_pickup_m')
                    ->label('Mesafe (m)')
                    ->numeric()
                    ->color(fn ($state) => $state > 100 ? 'danger' : 'success')
                    ->placeholder('—'),

                TextColumn::make('compensation_amount')
                    ->label('Tazminat')
                    ->money('TRY', locale: 'tr')
                    ->placeholder('—'),

                TextColumn::make('resolution')
                    ->label('Karar')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'confirmed'      => 'success',
                        'refunded'       => 'info',
                        'pending_review' => 'warning',
                        'disputed'       => 'danger',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'confirmed'      => 'Onaylandı',
                        'refunded'       => 'İade edildi',
                        'pending_review' => 'İnceleniyor',
                        'disputed'       => 'İtiraz',
                        default          => $s,
                    }),

                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->since()
                    ->sortable(),

                TextColumn::make('driver_note')
                    ->label('Sürücü notu')
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 240px;'])
                    ->toggleable(),

                TextColumn::make('admin_note')
                    ->label('Admin notu')
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 240px;'])
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('resolution')
                    ->label('Karar')
                    ->options([
                        'pending_review' => 'İnceleniyor',
                        'confirmed'      => 'Onaylandı',
                        'refunded'       => 'İade edildi',
                        'disputed'       => 'İtiraz',
                    ])
                    ->default('pending_review'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('confirm')
                    ->label('Onayla')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (NoShowReport $r) => $r->resolution === 'pending_review')
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('Admin notu (opsiyonel)')
                            ->rows(2),
                    ])
                    ->action(function (NoShowReport $r, array $data) {
                        $r->update([
                            'resolution' => 'confirmed',
                            'admin_note' => $data['admin_note'] ?? $r->admin_note,
                        ]);
                        Notification::make()->success()->title('Onaylandı')->send();
                    }),
                Action::make('refund')
                    ->label('İade et')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft)
                    ->color('info')
                    ->visible(fn (NoShowReport $r) => $r->resolution === 'pending_review')
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('İade gerekçesi')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (NoShowReport $r, array $data) {
                        $r->update([
                            'resolution' => 'refunded',
                            'admin_note' => $data['admin_note'],
                        ]);
                        Notification::make()->info()->title('Yolcuya iade edildi')->send();
                    }),
                Action::make('dispute')
                    ->label('İtiraz')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedExclamationCircle)
                    ->color('danger')
                    ->visible(fn (NoShowReport $r) => $r->resolution === 'pending_review')
                    ->action(function (NoShowReport $r) {
                        $r->update(['resolution' => 'disputed']);
                        Notification::make()->warning()->title('İtiraz olarak işaretlendi')->send();
                    }),
            ]);
    }
}
