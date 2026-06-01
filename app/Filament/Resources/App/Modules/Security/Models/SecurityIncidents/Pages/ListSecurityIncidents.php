<?php

namespace App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Pages;

use App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\SecurityIncidentResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityIncidents extends ListRecords
{
    protected static string $resource = SecurityIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getPollingInterval(): ?string
    {
        return '15s';
    }
}
