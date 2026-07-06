<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Compensations\Pages;

use App\Filament\Resources\App\Modules\Payment\Models\Compensations\DriverCompensationResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverCompensations extends ListRecords
{
    protected static string $resource = DriverCompensationResource::class;
}
