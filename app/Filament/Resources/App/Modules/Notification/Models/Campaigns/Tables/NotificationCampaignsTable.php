<?php

namespace App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Tables;

use App\Modules\Notification\Models\NotificationCampaign;
use App\Modules\Notification\Services\NotificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NotificationCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tür')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'promo' => 'success',
                        'info'  => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => NotificationCampaign::TYPES[$state] ?? $state),

                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (NotificationCampaign $r): ?string => \Illuminate\Support\Str::limit($r->body, 60)),

                TextColumn::make('audience')
                    ->label('Kitle')
                    ->formatStateUsing(fn (string $state): string => NotificationCampaign::AUDIENCES[$state] ?? $state)
                    ->color('gray'),

                IconColumn::make('show_as_popup')
                    ->label('Popup')
                    ->boolean(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent'      => 'success',
                        'scheduled' => 'warning',
                        'sending'   => 'info',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => NotificationCampaign::STATUSES[$state] ?? $state),

                TextColumn::make('recipients_count')->label('Hedef')->numeric()->alignEnd(),
                TextColumn::make('sent_count')->label('Push')->numeric()->alignEnd()->color('gray'),

                TextColumn::make('scheduled_at')
                    ->label('Zamanlama')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('sent_at')
                    ->label('Gönderildi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(NotificationCampaign::STATUSES),
                SelectFilter::make('audience')->label('Kitle')->options(NotificationCampaign::AUDIENCES),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('send')
                    ->label('Şimdi Gönder')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (NotificationCampaign $r): bool => in_array($r->status, ['draft', 'scheduled'], true))
                    ->requiresConfirmation()
                    ->modalHeading('Bildirimi şimdi gönder')
                    ->modalDescription(fn (NotificationCampaign $r): string => 'Hedef kitle: ' . (NotificationCampaign::AUDIENCES[$r->audience] ?? $r->audience) . '. Bu işlem geri alınamaz.')
                    ->action(function (NotificationCampaign $record) {
                        $service = app(NotificationService::class);
                        $fresh = $service->dispatchCampaign($record);
                        Notification::make()
                            ->title('Bildirim gönderildi')
                            ->body($fresh->recipients_count . ' kullanıcıya iletildi (' . $fresh->sent_count . ' push).')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
