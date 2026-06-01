<?php

namespace App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents;

use App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Pages\ListSecurityIncidents;
use App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Pages\ViewSecurityIncident;
use App\Filament\Resources\App\Modules\Security\Models\SecurityIncidents\Tables\SecurityIncidentsTable;
use App\Modules\Security\Models\SecurityIncident;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SecurityIncidentResource extends Resource
{
    protected static ?string $model = SecurityIncident::class;
    protected static ?string $slug = 'security-incidents';
    protected static ?string $modelLabel = 'Güvenlik Olayı';
    protected static ?string $pluralModelLabel = 'Güvenlik Olayları';
    protected static ?string $navigationLabel = 'Güvenlik Olayları';
    protected static string|\UnitEnum|null $navigationGroup = 'Çağrı Merkezi';
    protected static ?int $navigationSort = 10;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    public static function getNavigationBadge(): ?string
    {
        $open = SecurityIncident::whereIn('status', [
            SecurityIncident::STATUS_OPEN,
            SecurityIncident::STATUS_INVESTIGATING,
        ])->count();
        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return SecurityIncidentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityIncidents::route('/'),
            'view'  => ViewSecurityIncident::route('/{record}'),
        ];
    }
}
