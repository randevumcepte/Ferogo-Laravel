<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Pages;

use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\PricingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingRule extends EditRecord
{
    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
