<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\Pages;

use App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\LegalConsentResource;
use Filament\Resources\Pages\ListRecords;

class ListLegalConsents extends ListRecords
{
    protected static string $resource = LegalConsentResource::class;

    protected function getHeaderActions(): array
    {
        return []; // Sadece sistem yaratabilir
    }
}
