<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payouts\Pages;

use App\Filament\Resources\App\Modules\Payment\Models\Payouts\DriverPayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverPayouts extends ListRecords
{
    protected static string $resource = DriverPayoutResource::class;
}
