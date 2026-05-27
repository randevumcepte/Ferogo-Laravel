<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payments\Pages;

use App\Filament\Resources\App\Modules\Payment\Models\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
}
