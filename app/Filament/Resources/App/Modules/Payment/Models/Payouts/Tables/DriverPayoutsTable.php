<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payouts\Tables;

use App\Modules\Payment\Models\DriverPayout;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriverPayoutsTable
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

                TextColumn::make('period_start')
                    ->label('Dönem')
                    ->formatStateUsing(fn ($state, $record) =>
                        $state?->format('d.m.Y') . ' – ' . $record->period_end?->format('d.m.Y')
                    ),

                TextColumn::make('total_rides')
                    ->label('Yolculuk')
                    ->numeric()
                    ->alignRight(),

                TextColumn::make('gross_amount')
                    ->label('Brüt')
                    ->money('TRY', locale: 'tr')
                    ->alignRight(),

                TextColumn::make('commission_amount')
                    ->label('Komisyon')
                    ->money('TRY', locale: 'tr')
                    ->color('gray')
                    ->alignRight(),

                TextColumn::make('net_amount')
                    ->label('Net')
                    ->money('TRY', locale: 'tr')
                    ->weight('bold')
                    ->color('success')
                    ->alignRight(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'paid'    => 'success',
                        'pending' => 'warning',
                        'failed'  => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'paid'    => 'Ödendi',
                        'pending' => 'Bekliyor',
                        'failed'  => 'Başarısız',
                        default   => $s,
                    }),

                TextColumn::make('paid_at')
                    ->label('Ödeme tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('payment_reference')
                    ->label('Ref.')
                    ->copyable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending' => 'Bekliyor',
                        'paid'    => 'Ödendi',
                        'failed'  => 'Başarısız',
                    ])
                    ->default('pending'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('markPaid')
                    ->label('Ödendi işaretle')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (DriverPayout $p) => $p->status === 'pending')
                    ->schema([
                        TextInput::make('payment_reference')
                            ->label('Banka referansı (opsiyonel)')
                            ->maxLength(120),
                    ])
                    ->action(function (DriverPayout $p, array $data) {
                        $p->update([
                            'status'            => 'paid',
                            'paid_at'           => now(),
                            'payment_reference' => $data['payment_reference'] ?? $p->payment_reference,
                        ]);
                        Notification::make()
                            ->success()
                            ->title('Ödendi olarak işaretlendi')
                            ->body('Net: ₺' . number_format((float) $p->net_amount, 2, ',', '.'))
                            ->send();
                    }),
            ]);
    }
}
