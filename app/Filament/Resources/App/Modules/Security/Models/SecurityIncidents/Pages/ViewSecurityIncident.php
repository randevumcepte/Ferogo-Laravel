<?php

namespace App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Pages;

use App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\SecurityIncidentResource;
use App\Modules\Security\Models\SecurityIncident;
use App\Modules\Security\Models\VerificationPhoto;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSecurityIncident extends ViewRecord
{
    protected static string $resource = SecurityIncidentResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->record->isOpen()) {
            $actions[] = Action::make('approve')
                ->label('✓ Onayla — Sürücü doğru, yolculuk devam')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => SecurityIncident::STATUS_RESOLVED_OK,
                        'handler_user_id' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
                    // Tüm fotoları approve et
                    $this->record->verificationPhotos()->update([
                        'status' => VerificationPhoto::STATUS_APPROVED,
                        'reviewed_by_user_id' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);
                    Notification::make()->title('Olay onaylandı, yolculuk devam ediyor.')->success()->send();
                });

            $actions[] = Action::make('suspend')
                ->label('🚫 Sürücüyü Askıya Al')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Sürücü tüm yolculuk akışından çıkarılacak ve hesabı dondurulacak. Bu işlem ciddi sonuçları olan bir işlemdir.')
                ->action(function () {
                    $this->record->update([
                        'status' => SecurityIncident::STATUS_RESOLVED_SUSPENDED,
                        'handler_user_id' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
                    if ($this->record->driver_id) {
                        \App\Modules\Driver\Models\Driver::where('id', $this->record->driver_id)
                            ->update([
                                'is_suspended' => true,
                                'suspended_at' => now(),
                                'suspended_reason' => 'Güvenlik olayı: ' . $this->record->type,
                                'suspended_by_user_id' => auth()->id(),
                                'suspended_via_incident_id' => $this->record->id,
                                'availability_status' => 'offline',
                            ]);
                    }
                    $this->record->verificationPhotos()->update([
                        'status' => VerificationPhoto::STATUS_REJECTED,
                        'reviewed_by_user_id' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);
                    Notification::make()->title('Sürücü askıya alındı.')->danger()->send();
                });

            $actions[] = Action::make('police')
                ->label('🚓 Polise Yönlendir')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => SecurityIncident::STATUS_ESCALATED_POLICE,
                        'handler_user_id' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
                });
        }

        return $actions;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Olay Özeti')
                ->schema([
                    TextEntry::make('public_id')->label('Olay ID')->copyable(),
                    TextEntry::make('type')->label('Tip')->badge(),
                    TextEntry::make('status')->label('Durum')->badge(),
                    TextEntry::make('severity')->label('Aciliyet')->badge(),
                    TextEntry::make('reported_by')->label('İhbar Eden')->badge(),
                    TextEntry::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i:s'),
                    TextEntry::make('reporter_note')->label('İhbar Notu')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(3),

            Section::make('İlgili Yolculuk & Sürücü')
                ->schema([
                    TextEntry::make('rideRequest.public_id')->label('Ride Request')->placeholder('—')->copyable(),
                    TextEntry::make('driver.user.name')->label('Sürücü')->placeholder('—'),
                    TextEntry::make('driver.user.phone')->label('Sürücü Telefon')->placeholder('—')->copyable(),
                    TextEntry::make('customer.name')->label('Yolcu')->placeholder('—'),
                ])
                ->columns(2),

            Section::make('Doğrulama Fotoğrafları (3 zorunlu)')
                ->schema([
                    RepeatableEntry::make('verificationPhotos')
                        ->schema([
                            ImageEntry::make('path')
                                ->label('')
                                ->disk('public')
                                ->square()
                                ->height(220),
                            TextEntry::make('type')
                                ->label('Tip')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'selfie'  => '🤳 Selfie',
                                    'vehicle' => '🚗 Araç',
                                    'plate'   => '🔖 Plaka',
                                    default   => $state,
                                }),
                            TextEntry::make('status')->label('İnceleme')->badge(),
                            TextEntry::make('captured_at')->label('Çekim')->dateTime('d.m.Y H:i:s'),
                            TextEntry::make('flash_used')
                                ->label('Flash')
                                ->formatStateUsing(fn ($state) => $state ? '✓' : '×'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->collapsed(false),

            Section::make('Çözüm')
                ->schema([
                    TextEntry::make('handler.name')->label('Operatör')->placeholder('—'),
                    TextEntry::make('resolved_at')->label('Çözüm Tarihi')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                    TextEntry::make('resolution_note')->label('Çözüm Notu')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }
}
