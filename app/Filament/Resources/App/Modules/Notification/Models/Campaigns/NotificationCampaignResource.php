<?php

namespace App\Filament\Resources\App\Modules\Notification\Models\Campaigns;

use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Pages\CreateNotificationCampaign;
use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Pages\EditNotificationCampaign;
use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Pages\ListNotificationCampaigns;
use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Schemas\NotificationCampaignForm;
use App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Tables\NotificationCampaignsTable;
use App\Modules\Notification\Models\NotificationCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NotificationCampaignResource extends Resource
{
    protected static ?string $model = NotificationCampaign::class;

    protected static ?string $slug = 'bildirimler';

    protected static ?string $modelLabel = 'Bildirim';

    protected static ?string $pluralModelLabel = 'Bildirimler';

    protected static ?string $navigationLabel = 'Bildirim / Kampanya';

    protected static string|\UnitEnum|null $navigationGroup = 'Pazarlama';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    public static function form(Schema $schema): Schema
    {
        return NotificationCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListNotificationCampaigns::route('/'),
            'create' => CreateNotificationCampaign::route('/create'),
            'edit'   => EditNotificationCampaign::route('/{record}/edit'),
        ];
    }
}
