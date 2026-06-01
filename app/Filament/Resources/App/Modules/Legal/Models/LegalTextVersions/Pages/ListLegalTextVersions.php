<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Pages;

use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\LegalTextVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegalTextVersions extends ListRecords
{
    protected static string $resource = LegalTextVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni Versiyon'),
        ];
    }
}
