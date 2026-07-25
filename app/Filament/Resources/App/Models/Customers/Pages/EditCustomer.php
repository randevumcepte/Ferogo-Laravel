<?php

namespace App\Filament\Resources\App\Models\Customers\Pages;

use App\Filament\Resources\App\Models\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
