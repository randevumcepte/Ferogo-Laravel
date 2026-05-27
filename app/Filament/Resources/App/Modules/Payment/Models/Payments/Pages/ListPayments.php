<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payments\Pages;

use App\Filament\Resources\App\Modules\Payment\Models\Payments\PaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
