<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages;

use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\AdvertisementResource;
use App\Filament\Widgets\AdByDeviceChart;
use App\Filament\Widgets\AdByDistrictChart;
use App\Filament\Widgets\AdClicksByPlacementChart;
use App\Filament\Widgets\AdEventsByHourChart;
use App\Filament\Widgets\AdStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdvertisements extends ListRecords
{
    protected static string $resource = AdvertisementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Reklam analitiği dashboard'u — liste sayfasının üstünde. */
    protected function getHeaderWidgets(): array
    {
        return [
            AdStatsOverview::class,
            AdClicksByPlacementChart::class,
            AdEventsByHourChart::class,
            AdByDistrictChart::class,
            AdByDeviceChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
