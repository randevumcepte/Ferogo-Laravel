<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\CustomerTrust\Tables;

use App\Modules\Booking\Models\CustomerTrust as TrustModel;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomerTrustTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('trust_score')
                    ->label('Skor')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state >= 70 => 'success',
                        (int) $state >= 25 => 'gray',
                        default            => 'danger',
                    })
                    ->suffix('/100'),

                TextColumn::make('total_requests')
                    ->label('Talep')
                    ->numeric(),
                TextColumn::make('total_completed')
                    ->label('Tamamlanan')
                    ->numeric()
                    ->color('success'),
                TextColumn::make('total_no_shows')
                    ->label('No-Show')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('total_customer_cancellations')
                    ->label('İptal')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('is_blacklisted')
                    ->label('Kara Liste')
                    ->badge()
                    ->color(fn ($state) => $state ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? 'EVET' : '—'),

                TextColumn::make('banned_until')
                    ->label('Yasak bitiş')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('last_request_at')
                    ->label('Son talep')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('low_score')
                    ->label('Düşük skorlu (<25)')
                    ->query(fn ($query) => $query->where('trust_score', '<', 25)),
                TernaryFilter::make('is_blacklisted')
                    ->label('Kara liste'),
                SelectFilter::make('has_no_shows')
                    ->label('No-show durumu')
                    ->options([
                        'has' => 'No-show var',
                        'none' => 'Hiç yok',
                    ])
                    ->query(function ($query, array $data) {
                        if (($data['value'] ?? null) === 'has') return $query->where('total_no_shows', '>', 0);
                        if (($data['value'] ?? null) === 'none') return $query->where('total_no_shows', 0);
                        return $query;
                    }),
            ])
            ->defaultSort('trust_score', 'asc')
            ->recordActions([
                Action::make('ban')
                    ->label('Yasakla')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (TrustModel $t) => ! $t->banned_until || $t->banned_until->isPast())
                    ->schema([
                        DateTimePicker::make('banned_until')
                            ->label('Ne zamana kadar?')
                            ->required()
                            ->default(now()->addDays(7)),
                        Textarea::make('ban_reason')
                            ->label('Sebep')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (TrustModel $t, array $data) {
                        $t->update([
                            'banned_until' => $data['banned_until'],
                            'ban_reason'   => $data['ban_reason'],
                        ]);
                        Notification::make()->warning()->title('Müşteri yasaklandı')->send();
                    }),
                Action::make('unban')
                    ->label('Yasak kaldır')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (TrustModel $t) => $t->banned_until && $t->banned_until->isFuture())
                    ->requiresConfirmation()
                    ->action(function (TrustModel $t) {
                        $t->update(['banned_until' => null, 'ban_reason' => null]);
                        Notification::make()->success()->title('Yasak kaldırıldı')->send();
                    }),
                Action::make('blacklist')
                    ->label('Kara listeye al')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->visible(fn (TrustModel $t) => ! $t->is_blacklisted)
                    ->schema([
                        Textarea::make('blacklist_reason')
                            ->label('Sebep')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (TrustModel $t, array $data) {
                        $t->update([
                            'is_blacklisted'   => true,
                            'blacklisted_at'   => now(),
                            'blacklist_reason' => $data['blacklist_reason'],
                        ]);
                        Notification::make()->danger()->title('Kara listeye alındı')->send();
                    }),
                Action::make('unblacklist')
                    ->label('Kara listeden çıkar')
                    ->color('success')
                    ->visible(fn (TrustModel $t) => (bool) $t->is_blacklisted)
                    ->requiresConfirmation()
                    ->action(function (TrustModel $t) {
                        $t->update(['is_blacklisted' => false, 'blacklisted_at' => null, 'blacklist_reason' => null]);
                        Notification::make()->success()->title('Kara listeden çıkarıldı')->send();
                    }),
            ]);
    }
}
