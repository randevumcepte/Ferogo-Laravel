<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles;

use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Tables\VehiclesTable;
use App\Modules\Vehicle\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $slug = 'vehicles';

    protected static ?string $modelLabel = 'Araç';

    protected static ?string $pluralModelLabel = 'Araçlar';

    protected static ?string $navigationLabel = 'Araçlar';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
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
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
