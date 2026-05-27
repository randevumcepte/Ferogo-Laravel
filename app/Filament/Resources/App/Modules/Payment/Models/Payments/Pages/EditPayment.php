<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payments\Pages;

use App\Filament\Resources\App\Modules\Payment\Models\Payments\PaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
