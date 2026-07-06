<?php

namespace App\Filament\Widgets;

use App\Modules\Booking\Models\Ride;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Son yolculuklar — canlı akış. Süper admin son işlem/talep hareketini görsün.
 */
class RecentRides extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string|Htmlable|null
    {
        return 'Son Yolculuklar';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Ride::query()
                ->with(['driver.user', 'customer', 'vehicleClass'])
                ->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable()->width('60px'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ne zaman')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Yolcu')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('driver.user.name')
                    ->label('Sürücü')
                    ->placeholder('atanmamış')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vehicleClass.name')
                    ->label('Sınıf')
                    ->badge()
                    ->color(fn (?string $s): string => match ($s) {
                        'VIP'      => 'warning',
                        'Platinum' => 'info',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pickup_address')
                    ->label('Rota')
                    ->formatStateUsing(fn ($state, $record) =>
                        \Illuminate\Support\Str::limit($state, 40) . ' → ' .
                        \Illuminate\Support\Str::limit($record->dropoff_address ?? '', 40)
                    )
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 340px;']),

                Tables\Columns\TextColumn::make('total_fare')
                    ->label('Ücret')
                    ->money('TRY', locale: 'tr'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'completed'       => 'success',
                        'cancelled'       => 'gray',
                        'no_show'         => 'danger',
                        'in_progress'     => 'primary',
                        'driver_arriving' => 'warning',
                        'assigned'        => 'info',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'completed'       => 'Tamamlandı',
                        'cancelled'       => 'İptal',
                        'no_show'         => 'No-Show',
                        'in_progress'     => 'Yolculukta',
                        'driver_arriving' => 'Yolda',
                        'assigned'        => 'Atandı',
                        'pending'         => 'Bekliyor',
                        'searching'       => 'Aranıyor',
                        default           => $s,
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Aç')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Ride $r) => url('/admin/rides/' . $r->id . '/edit'))
                    ->openUrlInNewTab(false),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
