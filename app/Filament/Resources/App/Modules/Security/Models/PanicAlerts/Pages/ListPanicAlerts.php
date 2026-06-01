<?php

namespace App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Pages;

use App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\PanicAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListPanicAlerts extends ListRecords
{
    protected static string $resource = PanicAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getPollingInterval(): ?string
    {
        return '10s'; // sürekli güncelle — yeni alarm hemen görünsün
    }
}
