<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Applications\Pages;

use App\Filament\Resources\App\Modules\Driver\Models\Applications\DriverApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverApplications extends ListRecords
{
    protected static string $resource = DriverApplicationResource::class;
}
