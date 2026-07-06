<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FleetHealthAlerts;
use App\Filament\Widgets\LiveOpsStats;
use App\Filament\Widgets\PendingActionsList;
use App\Filament\Widgets\RecentRides;
use App\Filament\Widgets\Revenue30DaysChart;
use App\Filament\Widgets\SafetyAlerts;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * FerXGo süper admin kontrol merkezi — anlık operasyon KPI, gelir, filo sağlığı,
 * güvenlik alarmları ve bekleyen aksiyonlar tek ekranda.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Kontrol Merkezi';

    protected static ?string $navigationLabel = 'Kontrol Merkezi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -100;

    public function getWidgets(): array
    {
        return [
            LiveOpsStats::class,
            Revenue30DaysChart::class,
            PendingActionsList::class,
            FleetHealthAlerts::class,
            SafetyAlerts::class,
            RecentRides::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md'      => 2,
            'xl'      => 3,
        ];
    }
}
