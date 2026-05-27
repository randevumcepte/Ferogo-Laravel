<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\Rides;

use App\Filament\Resources\App\Modules\Booking\Models\Rides\Pages\CreateRide;
use App\Filament\Resources\App\Modules\Booking\Models\Rides\Pages\EditRide;
use App\Filament\Resources\App\Modules\Booking\Models\Rides\Pages\ListRides;
use App\Filament\Resources\App\Modules\Booking\Models\Rides\Schemas\RideForm;
use App\Filament\Resources\App\Modules\Booking\Models\Rides\Tables\RidesTable;
use App\Modules\Booking\Models\Ride;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $slug = 'rides';

    protected static ?string $modelLabel = 'Rezervasyon';

    protected static ?string $pluralModelLabel = 'Rezervasyonlar';

    protected static ?string $navigationLabel = 'Rezervasyonlar';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return RideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RidesTable::configure($table);
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
            'index' => ListRides::route('/'),
            'create' => CreateRide::route('/create'),
            'edit' => EditRide::route('/{record}/edit'),
        ];
    }
}
