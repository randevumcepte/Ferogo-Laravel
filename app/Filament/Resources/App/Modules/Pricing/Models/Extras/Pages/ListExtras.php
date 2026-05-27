<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\Extras\Pages;

use App\Filament\Resources\App\Modules\Pricing\Models\Extras\ExtraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtras extends ListRecords
{
    protected static string $resource = ExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
