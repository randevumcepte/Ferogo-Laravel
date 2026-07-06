<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payouts;

use App\Filament\Resources\App\Modules\Payment\Models\Payouts\Pages\ListDriverPayouts;
use App\Filament\Resources\App\Modules\Payment\Models\Payouts\Tables\DriverPayoutsTable;
use App\Modules\Payment\Models\DriverPayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverPayoutResource extends Resource
{
    protected static ?string $model = DriverPayout::class;

    protected static ?string $slug = 'driver-payouts';

    protected static ?string $modelLabel = 'Sürücü Ödemesi';

    protected static ?string $pluralModelLabel = 'Sürücü Ödemeleri';

    protected static ?string $navigationLabel = 'Haftalık Ödemeler';

    protected static string|\UnitEnum|null $navigationGroup = 'Finans';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationBadge(): ?string
    {
        $c = DriverPayout::where('status', 'pending')->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return DriverPayoutsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverPayouts::route('/'),
        ];
    }
}
