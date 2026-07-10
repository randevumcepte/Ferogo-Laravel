<?php

namespace App\Filament\Widgets;

use App\Modules\Security\Models\PanicAlert;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Güvenlik uyarıları — açık panik alarmlar ve son 24 saatin güvenlik olayları.
 * Süper admin kritik durumu ilk tokatta görsün.
 */
class SafetyAlerts extends BaseWidget
{
    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string|Htmlable|null
    {
        return '🛡️ Güvenlik — Aktif Panik Alarmlar & Son Olaylar';
    }

    public function table(Table $table): Table
    {
        if (! class_exists(PanicAlert::class)) {
            return $table->query(fn () => \App\Modules\Driver\Models\Driver::query()->whereRaw('1=0'));
        }

        $openStatuses = [
            PanicAlert::STATUS_TRIGGERED,
            PanicAlert::STATUS_ACKNOWLEDGED,
            PanicAlert::STATUS_CONTACTING,
            PanicAlert::STATUS_POLICE_DISPATCHED,
        ];

        return $table
            ->query(fn () => PanicAlert::query()
                ->with('ride.customer', 'driver.user', 'triggeredByUser')
                ->where(function ($q) use ($openStatuses) {
                    $q->whereIn('status', $openStatuses)
                      ->orWhere('created_at', '>=', now()->subDay());
                })
                ->latest())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->width('50px'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'triggered'         => 'danger',
                        'police_dispatched' => 'danger',
                        'acknowledged'      => 'warning',
                        'contacting'        => 'warning',
                        'resolved'          => 'success',
                        'false_alarm'       => 'gray',
                        default             => 'warning',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'triggered'         => '🚨 ACİL',
                        'acknowledged'      => 'Görüldü',
                        'contacting'        => 'Aranıyor',
                        'police_dispatched' => 'Polis Çağrıldı',
                        'resolved'          => 'Çözüldü',
                        'false_alarm'       => 'Yanlış alarm',
                        default             => $s,
                    }),

                Tables\Columns\TextColumn::make('triggered_by_type')
                    ->label('Kim')
                    ->badge()
                    ->color(fn (string $s): string => $s === 'driver' ? 'warning' : 'info')
                    ->formatStateUsing(fn (string $s): string => $s === 'driver' ? 'Sürücü' : 'Yolcu'),

                Tables\Columns\TextColumn::make('triggered_by_phone')
                    ->label('Telefon')
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('person_name')
                    ->label('Ad Soyad')
                    ->state(fn (PanicAlert $a) => $a->triggered_by_type === 'driver'
                        ? ($a->driver?->user?->name ?? $a->triggeredByUser?->name)
                        : ($a->triggeredByUser?->name ?? $a->ride?->customer?->name))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lat')
                    ->label('Konum')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state || ! $record->lng) return '—';
                        return sprintf('📍 %.4f, %.4f', (float) $state, (float) $record->lng);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ne zaman')
                    ->since(),
            ])
            ->recordActions([
                Action::make('map')
                    ->label('Haritada aç')
                    ->icon('heroicon-o-map')
                    ->url(fn (PanicAlert $a) => 'https://www.google.com/maps?q=' . $a->lat . ',' . $a->lng)
                    ->openUrlInNewTab()
                    ->visible(fn (PanicAlert $a) => $a->lat && $a->lng),
                Action::make('open')
                    ->label('Aç')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(url('/admin/panic-alerts')),
            ])
            ->emptyStateHeading('Aktif alarm yok · son 24 saat temiz')
            ->emptyStateDescription('Tüm platformda güvenlik olayı raporlanmadı.')
            ->paginated([5, 10]);
    }
}
