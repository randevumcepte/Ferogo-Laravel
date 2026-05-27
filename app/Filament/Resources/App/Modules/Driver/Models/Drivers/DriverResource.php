<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers;

use App\Filament\Resources\App\Modules\Driver\Models\Drivers\Pages\CreateDriver;
use App\Filament\Resources\App\Modules\Driver\Models\Drivers\Pages\EditDriver;
use App\Filament\Resources\App\Modules\Driver\Models\Drivers\Pages\ListDrivers;
use App\Filament\Resources\App\Modules\Driver\Models\Drivers\Schemas\DriverForm;
use App\Filament\Resources\App\Modules\Driver\Models\Drivers\Tables\DriversTable;
use App\Modules\Driver\Models\Driver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $slug = 'drivers';

    protected static ?string $modelLabel = 'Sürücü';

    protected static ?string $pluralModelLabel = 'Sürücüler';

    protected static ?string $navigationLabel = 'Sürücüler';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return DriverForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
