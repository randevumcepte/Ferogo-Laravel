<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\ChangeRequests\Pages;

use App\Filament\Resources\App\Modules\Driver\Models\ChangeRequests\DriverChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverChangeRequests extends ListRecords
{
    protected static string $resource = DriverChangeRequestResource::class;
}
