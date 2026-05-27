<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses;

use App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Pages\CreateVehicleClass;
use App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Pages\EditVehicleClass;
use App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Pages\ListVehicleClasses;
use App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Schemas\VehicleClassForm;
use App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Tables\VehicleClassesTable;
use App\Modules\Vehicle\Models\VehicleClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VehicleClassResource extends Resource
{
    protected static ?string $model = VehicleClass::class;

    protected static ?string $slug = 'vehicle-classes';

    protected static ?string $modelLabel = 'Araç Sınıfı';

    protected static ?string $pluralModelLabel = 'Araç Sınıfları';

    protected static ?string $navigationLabel = 'Araç Sınıfları';

    protected static string|\UnitEnum|null $navigationGroup = 'Konfigürasyon';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return VehicleClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleClassesTable::configure($table);
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
            'index' => ListVehicleClasses::route('/'),
            'create' => CreateVehicleClass::route('/create'),
            'edit' => EditVehicleClass::route('/{record}/edit'),
        ];
    }
}
