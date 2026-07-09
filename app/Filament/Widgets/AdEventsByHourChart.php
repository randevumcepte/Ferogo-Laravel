<?php

namespace App\Filament\Widgets;

use App\Modules\Marketing\Models\AdEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Saate göre (0–23) gösterim & tıklama — hangi saatlerde reklam daha çok görülüyor.
 */
class AdEventsByHourChart extends ChartWidget
{
    protected ?string $heading = 'Saate Göre Reklam Etkileşimi';

    protected ?string $description = 'Gün içi 0–23 saat dağılımı';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $imp = AdEvent::where('type', 'impression')
            ->select('hour', DB::raw('count(*) as c'))->groupBy('hour')->pluck('c', 'hour')->toArray();
        $clk = AdEvent::where('type', 'click')
            ->select('hour', DB::raw('count(*) as c'))->groupBy('hour')->pluck('c', 'hour')->toArray();

        $labels = [];
        $impData = [];
        $clkData = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
            $impData[] = (int) ($imp[$h] ?? 0);
            $clkData[] = (int) ($clk[$h] ?? 0);
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
