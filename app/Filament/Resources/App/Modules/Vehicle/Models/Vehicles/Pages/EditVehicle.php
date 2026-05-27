<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Pages;

use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\VehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
