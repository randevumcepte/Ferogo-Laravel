<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Packages;

use App\Filament\Resources\App\Modules\Payment\Models\Packages\Pages\ListDriverPackages;
use App\Filament\Resources\App\Modules\Payment\Models\Packages\Tables\DriverPackagesTable;
use App\Modules\Payment\Models\DriverPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverPackageResource extends Resource
{
    protected static ?string $model = DriverPackage::class;

    protected static ?string $slug = 'driver-packages';

    protected static ?string $modelLabel = 'Sürücü Paketi';

    protected static ?string $pluralModelLabel = 'Sürücü Paketleri';

    protected static ?string $navigationLabel = 'Sürücü Paketleri';

    protected static string|\UnitEnum|null $navigationGroup = 'Finans';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function getNavigationBadge(): ?string
    {
        $c = DriverPackage::where('status', 'active')->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function table(Table $table): Table
    {
        return DriverPackagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverPackages::route('/'),
        ];
    }
}
