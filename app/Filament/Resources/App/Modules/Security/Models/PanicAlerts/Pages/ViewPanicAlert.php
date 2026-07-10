<?php

namespace App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Pages;

use App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\PanicAlertResource;
use App\Modules\Security\Models\PanicAlert;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

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

    // ─────────────────────────────────────────────────────────────
    // Acil ekranda "ilk bakışta" görülmesi gereken bilgiyi türeten
    // yardımcılar. Panik yolcudan da sürücüden de gelebilir; adı/telefonu
    // ilgili ilişkiden (rideRequest / driver->user) toparlıyoruz.
    // ─────────────────────────────────────────────────────────────

    protected function isDriverAlert(PanicAlert $r): bool
    {
        return $r->triggered_by_type === PanicAlert::TRIGGER_DRIVER;
    }

    /** Paniği BAŞLATAN kişinin adı. */
    protected function personName(PanicAlert $r): string
    {
        $name = $this->isDriverAlert($r)
            ? ($r->driver?->user?->name ?? $r->triggeredByUser?->name)
            : ($r->rideRequest?->customer_name ?? $r->triggeredByUser?->name);

        return $name ?: '(isim bilinmiyor)';
    }

    /** Paniği başlatan kişinin telefonu. */
    protected function personPhone(PanicAlert $r): ?string
    {
        return $r->triggered_by_phone
            ?? ($this->isDriverAlert($r)
                ? ($r->driver?->user?->phone)
                : ($r->rideRequest?->customer_phone))
            ?? $r->triggeredByUser?->phone;
    }

    /** Yolculuktaki KARŞI taraf (panik yolcudansa sürücü, sürücüdense yolcu). */
    protected function counterName(PanicAlert $r): ?string
    {
        if ($this->isDriverAlert($r)) {
            return $r->rideRequest?->customer_name;
        }

        $driver = $r->driver ?? $r->rideRequest?->acceptedDriver;

        return $driver?->user?->name;
    }

    protected function counterPhone(PanicAlert $r): ?string
    {
        if ($this->isDriverAlert($r)) {
            return $r->rideRequest?->customer_phone;
        }

        $driver = $r->driver ?? $r->rideRequest?->acceptedDriver;

        return $driver?->user?->phone;
    }

    protected function counterPlate(PanicAlert $r): ?string
    {
        if ($this->isDriverAlert($r)) {
            return null; // karşı taraf yolcu → plaka yok
        }

        $driver = $r->driver ?? $r->rideRequest?->acceptedDriver;

        return $driver?->currentVehicle?->plate;
    }

    protected function mapsUrl(PanicAlert $r): ?string
    {
        return $r->lat
            ? 'https://www.google.com/maps?q=' . $r->lat . ',' . $r->lng
            : null;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            // ══════════════ 1) KİM — en kritik, en üstte, büyük ══════════════
            Section::make('🚨 KİM acil yardım istedi?')
                ->description('Aşağıdaki kişi tehlikede — önce bu kişiyi arayın.')
                ->schema([
                    TextEntry::make('who')
                        ->label('Kişi tipi')
                        ->state(fn (PanicAlert $r) => $this->isDriverAlert($r) ? 'SÜRÜCÜ' : 'YOLCU (Müşteri)')
                        ->badge()
                        ->size('lg')
                        ->color(fn (PanicAlert $r) => $this->isDriverAlert($r) ? 'info' : 'warning')
                        ->icon(fn (PanicAlert $r) => $this->isDriverAlert($r) ? 'heroicon-o-truck' : 'heroicon-o-user'),

                    TextEntry::make('person_name')
                        ->label('Ad Soyad')
                        ->state(fn (PanicAlert $r) => $this->personName($r))
                        ->weight('bold')
                        ->size('lg')
                        ->color('danger'),

                    TextEntry::make('person_phone')
                        ->label('Telefon — dokun & ara')
                        ->state(fn (PanicAlert $r) => $this->personPhone($r) ?? '—')
                        ->weight('bold')
                        ->size('lg')
                        ->icon('heroicon-o-phone')
                        ->copyable()
                        ->url(fn (PanicAlert $r) => $this->personPhone($r) ? 'tel:' . $this->personPhone($r) : null),

                    TextEntry::make('created_at')
                        ->label('Ne zaman')
                        ->state(fn (PanicAlert $r) => $r->created_at
                            ? $r->created_at->format('d.m.Y H:i:s') . '  (' . $r->created_at->diffForHumans() . ')'
                            : '—')
                        ->icon('heroicon-o-clock'),

                    TextEntry::make('status')
                        ->label('Alarm durumu')
                        ->badge()
                        ->formatStateUsing(fn (?string $state) => match ($state) {
                            PanicAlert::STATUS_TRIGGERED         => 'YENİ — TETİKLENDİ',
                            PanicAlert::STATUS_ACKNOWLEDGED      => 'İlgileniliyor',
                            PanicAlert::STATUS_CONTACTING        => 'Aranıyor',
                            PanicAlert::STATUS_POLICE_DISPATCHED => 'Polis çağrıldı',
                            PanicAlert::STATUS_RESOLVED          => 'Çözüldü',
                            PanicAlert::STATUS_FALSE_ALARM       => 'Yanlış alarm',
                            default                              => $state ?? '—',
                        })
                        ->color(fn (?string $state) => match ($state) {
                            PanicAlert::STATUS_TRIGGERED, PanicAlert::STATUS_POLICE_DISPATCHED => 'danger',
                            PanicAlert::STATUS_ACKNOWLEDGED, PanicAlert::STATUS_CONTACTING     => 'warning',
                            PanicAlert::STATUS_RESOLVED       => 'success',
                            PanicAlert::STATUS_FALSE_ALARM    => 'gray',
                            default                           => 'gray',
                        }),

                    TextEntry::make('handler.name')
                        ->label('İlgilenen operatör')
                        ->placeholder('— henüz yok'),
                ])
                ->columns(2),

            // ══════════════ 2) NEREDE — konum ══════════════
            Section::make('📍 NEREDE?')
                ->description('GPS varsa haritada açın; yoksa yolculuğun adreslerini kullanın.')
                ->schema([
                    TextEntry::make('maps')
                        ->label('Canlı GPS konumu')
                        ->state(fn (PanicAlert $r) => $this->mapsUrl($r) ? 'Haritada Aç →' : 'GPS konumu yok')
                        ->badge()
                        ->size('lg')
                        ->color(fn (PanicAlert $r) => $this->mapsUrl($r) ? 'success' : 'gray')
                        ->icon('heroicon-o-map-pin')
                        ->url(fn (PanicAlert $r) => $this->mapsUrl($r))
                        ->openUrlInNewTab(),

                    TextEntry::make('location_accuracy_m')
                        ->label('GPS doğruluğu (m)')
                        ->placeholder('—'),

                    TextEntry::make('rideRequest.pickup_address')
                        ->label('Biniş adresi (nereden)')
                        ->placeholder('—')
                        ->icon('heroicon-o-map-pin')
                        ->columnSpanFull(),

                    TextEntry::make('rideRequest.dropoff_address')
                        ->label('Varış adresi (nereye)')
                        ->placeholder('—')
                        ->icon('heroicon-o-flag')
                        ->columnSpanFull(),

                    TextEntry::make('lat')->label('Enlem')->copyable()->placeholder('—'),
                    TextEntry::make('lng')->label('Boylam')->copyable()->placeholder('—'),
                ])
                ->columns(2),

            // ══════════════ 3) Karşı taraf ══════════════
            Section::make('👥 Yolculuktaki diğer kişi')
                ->description(fn (PanicAlert $r) => $this->isDriverAlert($r)
                    ? 'Panik sürücüden geldi — aşağıdaki yolcu araçtaydı.'
                    : 'Panik yolcudan geldi — aşağıdaki sürücü ile yolculuktaydı.')
                ->schema([
                    TextEntry::make('counter_type')
                        ->label('Kim')
                        ->state(fn (PanicAlert $r) => $this->isDriverAlert($r) ? 'Yolcu (Müşteri)' : 'Sürücü')
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('counter_name')
                        ->label('Ad Soyad')
                        ->state(fn (PanicAlert $r) => $this->counterName($r) ?? '—')
                        ->weight('bold'),

                    TextEntry::make('counter_phone')
                        ->label('Telefon')
                        ->state(fn (PanicAlert $r) => $this->counterPhone($r) ?? '—')
                        ->icon('heroicon-o-phone')
                        ->copyable()
                        ->url(fn (PanicAlert $r) => $this->counterPhone($r) ? 'tel:' . $this->counterPhone($r) : null),

                    TextEntry::make('counter_plate')
                        ->label('Plaka')
                        ->state(fn (PanicAlert $r) => $this->counterPlate($r) ?? '—')
                        ->badge()
                        ->color('gray')
                        ->visible(fn (PanicAlert $r) => ! $this->isDriverAlert($r)),
                ])
                ->columns(2),

            // ══════════════ 4) Operatör müdahale geçmişi ══════════════
            Section::make('🕑 Müdahale geçmişi & notlar')
                ->schema([
                    TextEntry::make('first_contact_at')->label('İlk aranma')->dateTime('d.m.Y H:i:s')->placeholder('— henüz yok'),
                    TextEntry::make('police_called_at')->label('Polis çağrıldı')->dateTime('d.m.Y H:i:s')->placeholder('— hayır'),
                    TextEntry::make('resolved_at')->label('Kapanış')->dateTime('d.m.Y H:i:s')->placeholder('— açık'),
                    TextEntry::make('operator_notes')->label('Operatör notları')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(3),

            // ══════════════ 5) Teknik / adli (varsayılan kapalı) ══════════════
            Section::make('🔧 Teknik & adli detay')
                ->description('Alarm ID, yolculuk kayıtları, cihaz/IP bilgileri.')
                ->schema([
                    TextEntry::make('public_id')->label('Alarm ID')->copyable(),
                    TextEntry::make('triggered_by_phone')->label('Kayıtlı tetikleyen telefon')->copyable()->placeholder('—'),
                    TextEntry::make('ride_request_id')->label('Ride Request ID')->placeholder('—'),
                    TextEntry::make('ride_id')->label('Ride ID')->placeholder('—'),
                    TextEntry::make('driver_id')->label('Sürücü ID')->placeholder('—'),
                    TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                    TextEntry::make('device_fingerprint')->label('Cihaz parmak izi')->placeholder('—'),
                    TextEntry::make('user_agent')->label('Tarayıcı')->columnSpanFull()->placeholder('—'),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }
}
