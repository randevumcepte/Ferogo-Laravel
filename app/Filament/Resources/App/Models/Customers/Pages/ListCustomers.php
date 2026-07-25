<?php

namespace App\Filament\Resources\App\Models\Customers\Pages;

use App\Filament\Resources\App\Models\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
