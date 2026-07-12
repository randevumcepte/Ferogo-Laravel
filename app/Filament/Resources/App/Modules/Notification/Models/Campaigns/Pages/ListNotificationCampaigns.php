<?php

namespace App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Pages;

use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\NotificationCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationCampaigns extends ListRecords
{
    protected static string $resource = NotificationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni Bildirim'),
        ];
    }
}
