<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages;

use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\AdvertisementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdvertisement extends EditRecord
{
    protected static string $resource = AdvertisementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
