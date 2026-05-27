<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Pages;

use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\VehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
