<?php

namespace App\Filament\Widgets;

use App\Modules\Marketing\Models\AdEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Cihaz kırılımı — mobil / masaüstü / uygulama.
 */
class AdByDeviceChart extends ChartWidget
{
    protected ?string $heading = 'Cihaz Dağılımı';

    protected ?string $description = 'Etkileşimlerin cihaz türü';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = AdEvent::select('device', DB::raw('count(*) as c'))
            ->groupBy('device')->pluck('c', 'device')->toArray();

        $labelMap = ['mobile' => 'Mobil', 'desktop' => 'Masaüstü', 'app' => 'Uygulama'];
        $labels = [];
        $data = [];
        foreach ($rows as $device => $c) {
            $labels[] = $labelMap[$device] ?? ($device ?: 'Bilinmiyor');
            $data[] = (int) $c;
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Etkileşim',
                'data' => $data,
                'backgroundColor' => ['#EDBA3E', 'rgba(59,130,246,0.7)', '#8a8a8a', '#34d399'],
            ]],
        ];
    }
}
