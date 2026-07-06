<?php

namespace App\Filament\Widgets;

use App\Modules\Booking\Models\Ride;
use Filament\Widgets\ChartWidget;

/**
 * Son 30 gün: günlük ciro çizgi grafiği. Süper admin trend & anomaliyi
 * bir bakışta görsün.
 */
class Revenue30DaysChart extends ChartWidget
{
    protected ?string $heading = 'Son 30 Gün — Günlük Ciro';

    protected ?string $pollingInterval = '5m';

    protected int|string|array $columnSpan = 2;

    protected ?string $description = 'Sadece tamamlanmış yolculuklar · TL';

    protected function getData(): array
    {
        $labels = [];
        $revenue = [];
        $rideCount = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('d M');

            $revenue[] = (float) Ride::where('status', 'completed')
                ->whereDate('completed_at', $day)
                ->sum('total_fare');

            $rideCount[] = (int) Ride::where('status', 'completed')
                ->whereDate('completed_at', $day)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ciro (₺)',
                    'data'  => $revenue,
                    'borderColor' => '#F0C040',
                    'backgroundColor' => 'rgba(240, 192, 64, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Yolculuk sayısı',
                    'data'  => $rideCount,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.05)',
                    'fill' => false,
                    'tension' => 0.35,
                    'borderDash' => [4, 4],
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'position' => 'left',
                    'ticks' => ['color' => '#F0C040'],
                ],
                'y1' => [
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                    'ticks' => ['color' => '#10b981'],
                ],
            ],
        ];
    }
}
