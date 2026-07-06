<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Compensations\Tables;

use App\Modules\Payment\Models\DriverCompensation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DriverCompensationsTable
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

                TextColumn::make('reason')
                    ->label('Sebep')
                    ->badge()
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        'no_show'          => 'Müşteri gelmedi',
                        'customer_cancel'  => 'Yolcu iptali',
                        'system_error'     => 'Sistem hatası',
                        default            => $s ?? '—',
                    }),

                TextColumn::make('amount')
                    ->label('Tutar')
                    ->money('TRY', locale: 'tr')
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'paid'     => 'success',
                        'pending'  => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'paid'     => 'Ödendi',
                        'pending'  => 'Bekliyor',
                        'approved' => 'Onaylandı',
                        'rejected' => 'Reddedildi',
                        default    => $s,
                    }),

                TextColumn::make('ride_id')
                    ->label('Yolculuk')
                    ->url(fn ($state) => $state ? url('/admin/rides/' . $state . '/edit') : null)
                    ->placeholder('—'),

                TextColumn::make('note')
                    ->label('Not')
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 260px;'])
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->label('Ödeme')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Talep')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending'  => 'Bekliyor',
                        'approved' => 'Onaylandı',
                        'paid'     => 'Ödendi',
                        'rejected' => 'Reddedildi',
                    ])
                    ->default('pending'),
                SelectFilter::make('reason')
                    ->label('Sebep')
                    ->options([
                        'no_show'         => 'Müşteri gelmedi',
                        'customer_cancel' => 'Yolcu iptali',
                        'system_error'    => 'Sistem hatası',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Onayla')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (DriverCompensation $c) => $c->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (DriverCompensation $c) {
                        $c->update([
                            'status'      => 'approved',
                            'approved_by' => Auth::id(),
                        ]);
                        Notification::make()->success()->title('Onaylandı')->send();
                    }),
                Action::make('markPaid')
                    ->label('Ödendi')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (DriverCompensation $c) => in_array($c->status, ['pending', 'approved'], true))
                    ->requiresConfirmation()
                    ->action(function (DriverCompensation $c) {
                        $c->update([
                            'status'  => 'paid',
                            'paid_at' => now(),
                        ]);
                        Notification::make()->success()->title('Ödendi')->send();
                    }),
                Action::make('reject')
                    ->label('Reddet')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (DriverCompensation $c) => $c->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (DriverCompensation $c) {
                        $c->update(['status' => 'rejected']);
                        Notification::make()->warning()->title('Reddedildi')->send();
                    }),
            ]);
    }
}
