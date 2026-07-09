<?php

namespace App\Filament\Widgets;

use App\Modules\Marketing\Models\AdEvent;
use App\Modules\Marketing\Models\Advertisement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Hangi reklam ALANINDAN kaç tıklama/gösterim geldi (placement kırılımı).
 */
class AdClicksByPlacementChart extends ChartWidget
{
    protected ?string $heading = 'Reklam Alanına Göre Performans';

    protected ?string $description = 'Her slotun gösterim ve tıklama sayısı';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $imp = AdEvent::where('type', 'impression')
            ->select('placement', DB::raw('count(*) as c'))->groupBy('placement')->pluck('c', 'placement')->toArray();
        $clk = AdEvent::where('type', 'click')
            ->select('placement', DB::raw('count(*) as c'))->groupBy('placement')->pluck('c', 'placement')->toArray();

        $labels = [];
        $impData = [];
        $clkData = [];
        foreach (Advertisement::PLACEMENTS as $key => $label) {
            $labels[] = $label;
            $impData[] = (int) ($imp[$key] ?? 0);
            $clkData[] = (int) ($clk[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Gösterim', 'data' => $impData, 'backgroundColor' => 'rgba(59,130,246,0.6)'],
                ['label' => 'Tıklama', 'data' => $clkData, 'backgroundColor' => '#EDBA3E'],
            ],
        ];
    }
}
