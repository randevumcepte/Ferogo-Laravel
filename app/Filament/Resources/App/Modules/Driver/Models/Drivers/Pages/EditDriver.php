<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Pages;

use App\Filament\Resources\App\Modules\Driver\Models\Drivers\DriverResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
