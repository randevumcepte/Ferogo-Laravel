<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\CustomerTrust\Pages;

use App\Filament\Resources\App\Modules\Booking\Models\CustomerTrust\CustomerTrustResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerTrust extends ListRecords
{
    protected static string $resource = CustomerTrustResource::class;
}
