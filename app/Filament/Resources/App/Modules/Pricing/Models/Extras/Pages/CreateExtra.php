<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\Extras\Pages;

use App\Filament\Resources\App\Modules\Pricing\Models\Extras\ExtraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExtra extends CreateRecord
{
    protected static string $resource = ExtraResource::class;
}
