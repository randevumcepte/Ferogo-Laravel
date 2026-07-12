<?php

namespace App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Pages;

use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\NotificationCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotificationCampaign extends CreateRecord
{
    protected static string $resource = NotificationCampaignResource::class;

    /**
     * scheduled_at girildiyse → otomatik "scheduled" (cron gönderir),
     * girilmediyse → "draft" (listeden "Şimdi Gönder" ile yollanır).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = ! empty($data['scheduled_at']) ? 'scheduled' : 'draft';
        return $data;
    }
}
