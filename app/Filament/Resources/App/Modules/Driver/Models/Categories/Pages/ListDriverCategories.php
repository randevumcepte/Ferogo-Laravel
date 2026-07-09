<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Categories\Pages;

use App\Filament\Resources\App\Modules\Driver\Models\Categories\DriverCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverCategories extends ListRecords
{
    protected static string $resource = DriverCategoryResource::class;
}
