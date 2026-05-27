<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\Rides\Pages;

use App\Filament\Resources\App\Modules\Booking\Models\Rides\RideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRides extends ListRecords
{
    protected static string $resource = RideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
