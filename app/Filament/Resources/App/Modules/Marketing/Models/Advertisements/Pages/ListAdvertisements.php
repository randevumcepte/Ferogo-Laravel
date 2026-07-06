<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages;

use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\AdvertisementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdvertisements extends ListRecords
{
    protected static string $resource = AdvertisementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
