<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Applications;

use App\Filament\Resources\App\Modules\Driver\Models\Applications\Pages\ListDriverApplications;
use App\Filament\Resources\App\Modules\Driver\Models\Applications\Tables\DriverApplicationsTable;
use App\Modules\Driver\Models\DriverApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverApplicationResource extends Resource
{
    protected static ?string $model = DriverApplication::class;

    protected static ?string $slug = 'driver-applications';

    protected static ?string $modelLabel = 'Sürücü Başvurusu';

    protected static ?string $pluralModelLabel = 'Sürücü Başvuruları';

    protected static ?string $navigationLabel = 'Sürücü Başvuruları';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return DriverApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverApplications::route('/'),
        ];
    }
}
