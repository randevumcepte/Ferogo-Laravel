<?php

namespace App\Filament\Resources\App\Modules\Shared\Models\Cities\Pages;

use App\Filament\Resources\App\Modules\Shared\Models\Cities\CityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
