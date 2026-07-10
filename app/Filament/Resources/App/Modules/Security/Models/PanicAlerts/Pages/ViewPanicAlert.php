<?php

namespace App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Pages;

use App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\PanicAlertResource;
use App\Modules\Security\Models\PanicAlert;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewPanicAlert extends ViewRecord
{
    protected static string $resource = PanicAlertResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->record->status === PanicAlert::STATUS_TRIGGERED) {
            $actions[] = Action::make('acknowledge')
                ->label('Aldım, ilgileniyorum')
                ->color('warning')
                ->action(function () {
                    $this->record->update([
                        'status' => PanicAlert::STATUS_ACKNOWLEDGED,
                        'handler_user_id' => auth()->id(),
                        'acknowledged_at' => now(),
                    ]);
                    Notification::make()->title('Alarm acknowledge edildi')->success()->send();
                });
        }

        if (in_array($this->record->status, [PanicAlert::STATUS_TRIGGERED, PanicAlert::STATUS_ACKNOWLEDGED], true)) {
            $actions[] = Action::make('contact')
                ->label('Aramaya başla')
                ->color('warning')
                ->action(function () {
                    $this->record->update([
                        'status' => PanicAlert::STATUS_CONTACTING,
                        'first_contact_at' => $this->record->first_contact_at ?? now(),
                    ]);
                });
        }

        if (! in_array($this->record->status, [PanicAlert::STATUS_RESOLVED, PanicAlert::STATUS_FALSE_ALARM], true)) {
            $actions[] = Action::make('police')
                ->label('🚓 Polis çağırdım (155)')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => PanicAlert::STATUS_POLICE_DISPATCHED,
                        'police_called_at' => now(),
                    ]);
                });

            $actions[] = Action::make('resolve')
                ->label('✓ Çözüldü')
                ->color('success')
                ->action(function () {
                    $this->record->update([
                        'status' => PanicAlert::STATUS_RESOLVED,
                        'resolved_at' => now(),
                    ]);
                });

            $actions[] = Action::make('false_alarm')
                ->label('Yanlış alarm')
                ->color('gray')
                ->action(function () {
                    $this->record->update([
                        'status' => PanicAlert::STATUS_FALSE_ALARM,
                        'resolved_at' => now(),
                    ]);
                });
        }

        return $actions;
    }

    public function infolist(Schema $schema): Schema
    {
        // Not: Filament v4 layout Section'ı bazı sürümlerde 500 verdiği için
        // kırılgan wrapper yerine düz (flat) alan listesi kullanıyoruz.
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('public_id')->label('Alarm ID')->copyable(),
                TextEntry::make('status')->label('Durum')->badge(),
                TextEntry::make('triggered_by_type')->label('Tetikleyen')->badge(),
                TextEntry::make('triggered_by_phone')->label('Telefon')->copyable()->placeholder('—'),
                TextEntry::make('created_at')->label('Tetiklenme')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('handler.name')->label('Operatör')->placeholder('—'),

                TextEntry::make('lat')->label('Enlem')->copyable()->placeholder('—'),
                TextEntry::make('lng')->label('Boylam')->copyable()->placeholder('—'),
                TextEntry::make('location_accuracy_m')->label('Doğruluk (m)')->placeholder('—'),
                TextEntry::make('google_maps')
                    ->label('Google Maps')
                    ->state(fn ($record) => $record->lat ? '📍 Haritada aç' : '—')
                    ->url(fn ($record) => $record->lat
                        ? 'https://www.google.com/maps?q=' . $record->lat . ',' . $record->lng
                        : null)
                    ->openUrlInNewTab(),

                TextEntry::make('ride_request_id')->label('Ride Request ID')->placeholder('—'),
                TextEntry::make('ride_id')->label('Ride ID')->placeholder('—'),
                TextEntry::make('driver_id')->label('Sürücü ID')->placeholder('—'),

                TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                TextEntry::make('device_fingerprint')->label('Cihaz parmak izi')->placeholder('—'),
                TextEntry::make('user_agent')->label('Tarayıcı')->placeholder('—')->columnSpanFull(),

                TextEntry::make('first_contact_at')->label('İlk aranma')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('police_called_at')->label('Polis çağrıldı')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('resolved_at')->label('Çözüldü')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('operator_notes')->label('Operatör notları')->placeholder('—')->columnSpanFull(),
            ]);
    }
}
