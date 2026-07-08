<?php

namespace App\Filament\Widgets;

use App\Modules\Marketing\Models\AdEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Reklam analitiği — toplam gösterim/tıklama/CTR/tekil ziyaretçi kartları.
 * Kaynak: ad_events (detaylı olay günlüğü).
 */
class AdStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $imp  = AdEvent::where('type', 'impression')->count();
        $clk  = AdEvent::where('type', 'click')->count();
        $ctr  = $imp > 0 ? round($clk / $imp * 100, 2) : 0;
        $uniq = AdEvent::whereNotNull('anon_id')->distinct('anon_id')->count('anon_id');

        return [
            Stat::make('Toplam Gösterim', number_format($imp, 0, ',', '.'))
                ->description('Reklamların görülme sayısı')
                ->color('info'),
            Stat::make('Toplam Tıklama', number_format($clk, 0, ',', '.'))
                ->description('Reklamlara tıklama')
                ->color('warning'),
            Stat::make('CTR', $ctr . '%')
                ->description('Tıklama ÷ Gösterim')
                ->color('success'),
            Stat::make('Tekil Ziyaretçi', number_format($uniq, 0, ',', '.'))
                ->description('Farklı kişi (çerez bazlı)')
                ->color('gray'),
        ];
    }
}
