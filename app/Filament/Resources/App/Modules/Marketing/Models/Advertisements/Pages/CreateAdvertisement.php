<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages;

use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\AdvertisementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvertisement extends CreateRecord
{
    protected static string $resource = AdvertisementResource::class;
}
