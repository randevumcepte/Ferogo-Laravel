<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Pages;

use App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\VehicleClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleClass extends EditRecord
{
    protected static string $resource = VehicleClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
