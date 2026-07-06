<?php

namespace App\Filament\Widgets;

use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\DriverChangeRequest;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Bekleyen sürücü başvuruları — direkt aksiyona geçilebilecek "onay kuyruğu".
 * Araç değişikliği talepleri başlıkta badge olarak sayı ile gösterilir (link).
 */
class PendingActionsList extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string|Htmlable|null
    {
        $changeCount = DriverChangeRequest::where('status', 'pending')->count();
        $suffix = $changeCount > 0
            ? " · {$changeCount} araç değişikliği talebi"
            : '';
        return 'Onay Kuyruğu — Bekleyen Sürücü Başvuruları' . $suffix;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DriverApplication::query()->where('status', 'pending')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Ad Soyad')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->copyable(),
                Tables\Columns\TextColumn::make('vehicle_info')
                    ->label('Araç')
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 260px;']),
                Tables\Columns\TextColumn::make('experience_band')
                    ->label('Deneyim')
                    ->badge()
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        'under_1' => '<1 yıl',
                        '1_to_3'  => '1-3 yıl',
                        '3_to_5'  => '3-5 yıl',
                        '5_plus'  => '5+ yıl',
                        default   => $s ?? '—',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Başvuru')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('İncele')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(url('/admin/driver-applications')),
            ])
            ->emptyStateHeading('Bekleyen başvuru yok')
            ->emptyStateDescription('Yeni sürücü başvuruları burada belirir.')
            ->paginated([5, 10, 25]);
    }
}
