<?php

namespace App\Filament\Resources\App\Modules\Shared\Models\Cities\Pages;

use App\Filament\Resources\App\Modules\Shared\Models\Cities\CityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
