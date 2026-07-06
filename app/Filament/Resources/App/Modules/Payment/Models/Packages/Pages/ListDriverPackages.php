<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Packages\Pages;

use App\Filament\Resources\App\Modules\Payment\Models\Packages\DriverPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverPackages extends ListRecords
{
    protected static string $resource = DriverPackageResource::class;
}
