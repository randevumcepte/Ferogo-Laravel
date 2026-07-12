<?php

namespace App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Pages;

use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\NotificationCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNotificationCampaign extends EditRecord
{
    protected static string $resource = NotificationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Gönderilmemiş kampanyalarda scheduled_at değişimini durum ile eşitle.
     * Gönderilmiş (sent) kampanyanın durumu korunur.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($this->record->status ?? null) !== 'sent') {
            $data['status'] = ! empty($data['scheduled_at']) ? 'scheduled' : 'draft';
        }
        return $data;
    }
}
