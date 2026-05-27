<?php

namespace App\Filament\Resources\App\Modules\Shared\Models\Cities\Pages;

use App\Filament\Resources\App\Modules\Shared\Models\Cities\CityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    protected static string $resource = CityResource::class;
}
