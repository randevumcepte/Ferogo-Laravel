<?php

namespace App\Filament\Widgets;

use App\Modules\Marketing\Models\AdEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * İlçeye göre görülme — müşteri hangi bölgeden etkileşti (İlk 10 ilçe).
 * Konum verisi yalnızca kullanıcı izin verdiği oturumlarda dolar.
 */
class AdByDistrictChart extends ChartWidget
{
    protected ?string $heading = 'İlçeye Göre Görülme (İlk 10)';

    protected ?string $description = 'İzmir ilçe kırılımı (konum izni olan etkileşimler)';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = AdEvent::whereNotNull('district')
            ->select('district', DB::raw('count(*) as c'))
            ->groupBy('district')->orderByDesc('c')->limit(10)
            ->pluck('c', 'district')->toArray();

        return [
            'labels' => array_keys($rows),
            'datasets' => [[
                'label' => 'Etkileşim',
                'data' => array_map('intval', array_values($rows)),
                'backgroundColor' => '#EDBA3E',
            ]],
        ];
    }
}
