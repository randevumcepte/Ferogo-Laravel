<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\Extras\Pages;

use App\Filament\Resources\App\Modules\Pricing\Models\Extras\ExtraResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtra extends EditRecord
{
    protected static string $resource = ExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
