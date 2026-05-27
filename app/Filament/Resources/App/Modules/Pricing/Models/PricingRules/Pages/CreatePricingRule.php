<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Pages;

use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\PricingRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePricingRule extends CreateRecord
{
    protected static string $resource = PricingRuleResource::class;
}
