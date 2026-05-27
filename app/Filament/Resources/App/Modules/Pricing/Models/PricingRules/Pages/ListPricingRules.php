<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Pages;

use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\PricingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingRules extends ListRecords
{
    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
