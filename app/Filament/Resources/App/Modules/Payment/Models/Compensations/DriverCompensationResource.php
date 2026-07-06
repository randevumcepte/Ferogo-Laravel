<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Compensations;

use App\Filament\Resources\App\Modules\Payment\Models\Compensations\Pages\ListDriverCompensations;
use App\Filament\Resources\App\Modules\Payment\Models\Compensations\Tables\DriverCompensationsTable;
use App\Modules\Payment\Models\DriverCompensation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverCompensationResource extends Resource
{
    protected static ?string $model = DriverCompensation::class;

    protected static ?string $slug = 'driver-compensations';

    protected static ?string $modelLabel = 'Sürücü Tazminatı';

    protected static ?string $pluralModelLabel = 'Sürücü Tazminatları';

    protected static ?string $navigationLabel = 'Tazminatlar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finans';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    public static function getNavigationBadge(): ?string
    {
        $c = DriverCompensation::where('status', 'pending')->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return DriverCompensationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverCompensations::route('/'),
        ];
    }
}
