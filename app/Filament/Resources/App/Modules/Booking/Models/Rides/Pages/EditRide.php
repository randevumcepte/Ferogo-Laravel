<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\Rides\Pages;

use App\Filament\Resources\App\Modules\Booking\Models\Rides\RideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRide extends EditRecord
{
    protected static string $resource = RideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
