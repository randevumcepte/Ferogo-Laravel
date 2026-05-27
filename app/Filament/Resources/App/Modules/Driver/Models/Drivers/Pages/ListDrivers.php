<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Pages;

use App\Filament\Resources\App\Modules\Driver\Models\Drivers\DriverResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
