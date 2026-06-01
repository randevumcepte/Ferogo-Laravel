<?php

namespace App\Filament\Resources\App\Modules\Security\Models\PanicAlerts;

use App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Pages\ListPanicAlerts;
use App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Pages\ViewPanicAlert;
use App\Filament\Resources\App\Modules\Security\Models\PanicAlerts\Tables\PanicAlertsTable;
use App\Modules\Security\Models\PanicAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PanicAlertResource extends Resource
{
    protected static ?string $model = PanicAlert::class;
    protected static ?string $slug = 'panic-alerts';
    protected static ?string $modelLabel = 'Acil Yardım';
    protected static ?string $pluralModelLabel = 'Acil Yardım Alarmları';
    protected static ?string $navigationLabel = 'Acil Yardım Alarmları';
    protected static string|\UnitEnum|null $navigationGroup = 'Çağrı Merkezi';
    protected static ?int $navigationSort = 5;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function getNavigationBadge(): ?string
    {
        $open = PanicAlert::whereIn('status', [
            PanicAlert::STATUS_TRIGGERED,
            PanicAlert::STATUS_ACKNOWLEDGED,
            PanicAlert::STATUS_CONTACTING,
        ])->count();
        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return PanicAlertsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPanicAlerts::route('/'),
            'view'  => ViewPanicAlert::route('/{record}'),
        ];
    }
}
