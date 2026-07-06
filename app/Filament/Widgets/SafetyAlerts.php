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
    protected static ?string $pollingInterval = '15s';

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

        return $table
            ->query(fn () => PanicAlert::query()
                ->with('ride.driver.user', 'ride.customer')
                ->where(function ($q) {
                    $q->where('status', 'active')
                      ->orWhere('created_at', '>=', now()->subDay());
                })
                ->latest())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->width('50px'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'active'    => 'danger',
                        'resolved'  => 'success',
                        'false_alarm' => 'gray',
                        default     => 'warning',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'active'      => 'ACİL',
                        'resolved'    => 'Çözüldü',
                        'false_alarm' => 'Yanlış alarm',
                        default       => $s,
                    }),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Kim')
                    ->badge(),

                Tables\Columns\TextColumn::make('ride.customer.name')
                    ->label('Yolcu')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('ride.driver.user.name')
                    ->label('Sürücü')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('latitude')
                    ->label('Konum')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state || ! $record->longitude) return '—';
                        return sprintf('%.4f, %.4f', (float) $state, (float) $record->longitude);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ne zaman')
                    ->since(),
            ])
            ->recordActions([
                Action::make('map')
                    ->label('Haritada aç')
                    ->icon('heroicon-o-map')
                    ->url(fn (PanicAlert $a) => 'https://www.google.com/maps?q=' . $a->latitude . ',' . $a->longitude)
                    ->openUrlInNewTab()
                    ->visible(fn (PanicAlert $a) => $a->latitude && $a->longitude),
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
