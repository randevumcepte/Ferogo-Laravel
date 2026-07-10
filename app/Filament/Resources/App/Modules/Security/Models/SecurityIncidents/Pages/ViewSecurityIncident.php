<?php

namespace App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Pages;

use App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\SecurityIncidentResource;
use App\Modules\Security\Models\SecurityIncident;
use App\Modules\Security\Models\VerificationPhoto;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
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
                    $this->redirect(SecurityIncidentResource::getUrl('index'));
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
                    $this->redirect(SecurityIncidentResource::getUrl('index'));
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
                    Notification::make()->title('Olay polise yönlendirildi olarak kapatıldı.')->success()->send();
                    $this->redirect(SecurityIncidentResource::getUrl('index'));
                });
        }

        return $actions;
    }

    // ─────────────── Türkçe etiket & renk eşlemeleri ───────────────

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            SecurityIncident::TYPE_VISUAL_MISMATCH  => 'Araç/sürücü fotoğrafı uyuşmuyor',
            SecurityIncident::TYPE_WRONG_VEHICLE    => 'Farklı araç geldi',
            SecurityIncident::TYPE_WRONG_DRIVER     => 'Sürücü kimliği uyuşmuyor',
            SecurityIncident::TYPE_DRIVER_NO_SHOW   => 'Sürücü gelmedi',
            SecurityIncident::TYPE_CUSTOMER_NO_SHOW => 'Yolcu yerinde yok',
            SecurityIncident::TYPE_SAFETY_CONCERN   => 'Güvenlik endişesi',
            SecurityIncident::TYPE_PANIC_BUTTON     => 'Acil yardım butonu',
            SecurityIncident::TYPE_OTHER            => 'Diğer',
            default                                 => $type ?? '—',
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            SecurityIncident::STATUS_OPEN               => 'Açık — bekliyor',
            SecurityIncident::STATUS_INVESTIGATING      => 'İnceleniyor',
            SecurityIncident::STATUS_RESOLVED_OK        => 'Çözüldü — sürücü doğru',
            SecurityIncident::STATUS_RESOLVED_SUSPENDED => 'Çözüldü — sürücü askıya alındı',
            SecurityIncident::STATUS_ESCALATED_POLICE   => 'Polise yönlendirildi',
            default                                     => $status ?? '—',
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            SecurityIncident::STATUS_OPEN               => 'danger',
            SecurityIncident::STATUS_INVESTIGATING      => 'warning',
            SecurityIncident::STATUS_RESOLVED_OK        => 'success',
            SecurityIncident::STATUS_RESOLVED_SUSPENDED => 'danger',
            SecurityIncident::STATUS_ESCALATED_POLICE   => 'danger',
            default                                     => 'gray',
        };
    }

    public static function severityLabel(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'KRİTİK',
            'high'     => 'Yüksek',
            'medium'   => 'Orta',
            'low'      => 'Düşük',
            default    => $severity ?? '—',
        };
    }

    public static function severityColor(?string $severity): string
    {
        return match ($severity) {
            'critical', 'high' => 'danger',
            'medium'           => 'warning',
            'low'              => 'gray',
            default            => 'gray',
        };
    }

    public static function reportedByLabel(?string $by): string
    {
        return match ($by) {
            'customer' => 'Yolcu (Müşteri)',
            'driver'   => 'Sürücü',
            'system'   => 'Sistem (otomatik)',
            'operator' => 'Operatör',
            'admin'    => 'Yönetici',
            default    => $by ?? '—',
        };
    }

    protected function customerName(SecurityIncident $r): ?string
    {
        return $r->customer?->name ?? $r->rideRequest?->customer_name;
    }

    protected function customerPhone(SecurityIncident $r): ?string
    {
        return $r->customer?->phone ?? $r->rideRequest?->customer_phone;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            // ══════════════ 1) Olay özeti ══════════════
            Section::make('🛡️ Olay Özeti')
                ->description('Ne oldu, ne kadar acil ve şu an hangi aşamada?')
                ->schema([
                    TextEntry::make('type')
                        ->label('Ne oldu?')
                        ->state(fn (SecurityIncident $r) => self::typeLabel($r->type))
                        ->badge()
                        ->size('lg')
                        ->color('danger')
                        ->columnSpanFull(),

                    TextEntry::make('severity')
                        ->label('Aciliyet')
                        ->state(fn (SecurityIncident $r) => self::severityLabel($r->severity))
                        ->badge()
                        ->color(fn (SecurityIncident $r) => self::severityColor($r->severity)),

                    TextEntry::make('status')
                        ->label('Durum')
                        ->state(fn (SecurityIncident $r) => self::statusLabel($r->status))
                        ->badge()
                        ->color(fn (SecurityIncident $r) => self::statusColor($r->status)),

                    TextEntry::make('reported_by')
                        ->label('İhbarı yapan')
                        ->state(fn (SecurityIncident $r) => self::reportedByLabel($r->reported_by))
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('created_at')
                        ->label('Ne zaman')
                        ->state(fn (SecurityIncident $r) => $r->created_at
                            ? $r->created_at->format('d.m.Y H:i:s') . '  (' . $r->created_at->diffForHumans() . ')'
                            : '—')
                        ->icon('heroicon-o-clock')
                        ->columnSpanFull(),

                    TextEntry::make('reporter_note')
                        ->label('İhbar notu / açıklama')
                        ->placeholder('— açıklama girilmemiş')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            // ══════════════ 2) İlgili yolculuk & kişiler ══════════════
            Section::make('🚗 İlgili Yolculuk & Kişiler')
                ->description('Olayın bağlı olduğu sürücü, yolcu ve yolculuk. Boşsa henüz eşleşme yok demektir.')
                ->schema([
                    TextEntry::make('driver.user.name')
                        ->label('Sürücü')
                        ->weight('bold')
                        ->placeholder('— sürücü atanmamış'),

                    TextEntry::make('driver.user.phone')
                        ->label('Sürücü telefon — dokun & ara')
                        ->icon('heroicon-o-phone')
                        ->copyable()
                        ->url(fn (SecurityIncident $r) => $r->driver?->user?->phone
                            ? 'tel:' . $r->driver->user->phone
                            : null)
                        ->placeholder('—'),

                    TextEntry::make('customer_name')
                        ->label('Yolcu')
                        ->state(fn (SecurityIncident $r) => $this->customerName($r) ?? '—')
                        ->weight('bold'),

                    TextEntry::make('customer_phone')
                        ->label('Yolcu telefon — dokun & ara')
                        ->state(fn (SecurityIncident $r) => $this->customerPhone($r) ?? '—')
                        ->icon('heroicon-o-phone')
                        ->copyable()
                        ->url(fn (SecurityIncident $r) => $this->customerPhone($r)
                            ? 'tel:' . $this->customerPhone($r)
                            : null),

                    TextEntry::make('driver_plate')
                        ->label('Araç plakası')
                        ->state(fn (SecurityIncident $r) => $r->driver?->currentVehicle?->plate ?? '—')
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('rideRequest.public_id')
                        ->label('Yolculuk talep no')
                        ->placeholder('— bağlı yolculuk yok')
                        ->copyable(),
                ])
                ->columns(2),

            // ══════════════ 3) Doğrulama fotoğrafları ══════════════
            Section::make('📸 Doğrulama Fotoğrafları')
                ->description('Olay sırasında sürücüden istenen selfie, araç ve plaka fotoğrafları.')
                ->schema([
                    RepeatableEntry::make('verificationPhotos')
                        ->hiddenLabel()
                        ->schema([
                            ImageEntry::make('path')
                                ->hiddenLabel()
                                ->disk('public')
                                ->square()
                                ->height(220),
                            TextEntry::make('type')
                                ->label('Fotoğraf türü')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    VerificationPhoto::TYPE_SELFIE  => '🤳 Sürücü selfie',
                                    VerificationPhoto::TYPE_VEHICLE => '🚗 Araç',
                                    VerificationPhoto::TYPE_PLATE   => '🔖 Plaka',
                                    default                         => $state ?? '—',
                                }),
                            TextEntry::make('status')
                                ->label('İnceleme durumu')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    VerificationPhoto::STATUS_PENDING_REVIEW => 'İnceleme bekliyor',
                                    VerificationPhoto::STATUS_APPROVED       => 'Onaylandı',
                                    VerificationPhoto::STATUS_REJECTED       => 'Reddedildi',
                                    VerificationPhoto::STATUS_EXPIRED        => 'Süresi doldu',
                                    default                                  => $state ?? '—',
                                })
                                ->color(fn ($state) => match ($state) {
                                    VerificationPhoto::STATUS_APPROVED => 'success',
                                    VerificationPhoto::STATUS_REJECTED => 'danger',
                                    VerificationPhoto::STATUS_EXPIRED  => 'gray',
                                    default                            => 'warning',
                                }),
                            TextEntry::make('captured_at')->label('Çekim zamanı')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                            TextEntry::make('flash_used')
                                ->label('Flaş kullanıldı')
                                ->formatStateUsing(fn ($state) => $state ? 'Evet' : 'Hayır'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->collapsed(false),

            // ══════════════ 4) Karar / çözüm ══════════════
            Section::make('✅ Karar & Çözüm')
                ->description('Olayı kim, ne zaman, nasıl kapattı?')
                ->schema([
                    TextEntry::make('handler.name')->label('İlgilenen operatör')->placeholder('— henüz yok'),
                    TextEntry::make('resolved_at')->label('Çözüm tarihi')->dateTime('d.m.Y H:i:s')->placeholder('— açık, çözülmedi'),
                    TextEntry::make('resolution_note')->label('Çözüm / karar notu')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(2),

            // ══════════════ 5) Teknik detay (kapalı) ══════════════
            Section::make('🔧 Teknik detay')
                ->schema([
                    TextEntry::make('public_id')->label('Olay ID')->copyable(),
                    TextEntry::make('ride_request_id')->label('Ride Request ID')->placeholder('—'),
                    TextEntry::make('ride_id')->label('Ride ID')->placeholder('—'),
                    TextEntry::make('driver_id')->label('Sürücü ID')->placeholder('—'),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }
}
