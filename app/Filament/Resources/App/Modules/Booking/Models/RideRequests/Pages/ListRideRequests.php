<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\RideRequests\Pages;

use App\Filament\Resources\App\Modules\Booking\Models\RideRequests\RideRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRideRequests extends ListRecords
{
    protected static string $resource = RideRequestResource::class;
}
