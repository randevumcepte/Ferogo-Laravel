<?php

namespace App\Filament\Widgets;

use App\Modules\Driver\Models\Driver;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filo sağlığı — belgesi biten / bitmek üzere olan sürücüler, düşük puanlı
 * sürücüler. Süper admin risk grubunu tek listede görür.
 */
class FleetHealthAlerts extends BaseWidget
{
    protected static ?string $pollingInterval = '5m';

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string|Htmlable|null
    {
        return 'Filo Sağlığı — Belgesi Biten / Bitmek Üzere Sürücüler';
    }

    public function table(Table $table): Table
    {
        $soon = now()->addDays(30);

        return $table
            ->query(fn () => Driver::query()
                ->with('user')
                ->where('approval_status', 'approved')
                ->where(function ($q) use ($soon) {
                    $q->where('license_expires_at', '<=', $soon)
                      ->orWhere('src_expires_at', '<=', $soon)
                      ->orWhere('insurance_expires_at', '<=', $soon)
                      ->orWhere('inspection_expires_at', '<=', $soon)
                      ->orWhere('rating', '<', 4.5);
                }))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sürücü')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Puan')
                    ->formatStateUsing(fn ($state) => '★ ' . number_format((float) $state, 2))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (float) $state >= 4.8 => 'success',
                        (float) $state >= 4.5 => 'warning',
                        default               => 'danger',
                    }),

                Tables\Columns\TextColumn::make('license_expires_at')
                    ->label('Ehliyet')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->isBefore(now()->addDays(30)) ? 'warning' : 'gray')),

                Tables\Columns\TextColumn::make('src_expires_at')
                    ->label('SRC')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->isBefore(now()->addDays(30)) ? 'warning' : 'gray')),

                Tables\Columns\TextColumn::make('insurance_expires_at')
                    ->label('Sigorta')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->isBefore(now()->addDays(30)) ? 'warning' : 'gray')),

                Tables\Columns\TextColumn::make('inspection_expires_at')
                    ->label('Muayene')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->isBefore(now()->addDays(30)) ? 'warning' : 'gray')),

                Tables\Columns\TextColumn::make('availability_status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'online'  => 'success',
                        'busy'    => 'warning',
                        'offline' => 'gray',
                        default   => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Aç')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Driver $d) => url('/admin/drivers/' . $d->id . '/edit'))
                    ->openUrlInNewTab(false),
            ])
            ->emptyStateHeading('Filo sağlıklı')
            ->emptyStateDescription('Tüm sürücülerin belgeleri güncel ve puanları iyi.')
            ->paginated([5, 10, 25]);
    }
}
