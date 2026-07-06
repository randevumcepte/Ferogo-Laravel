<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements;

use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages\CreateAdvertisement;
use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages\EditAdvertisement;
use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Pages\ListAdvertisements;
use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Schemas\AdvertisementForm;
use App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Tables\AdvertisementsTable;
use App\Modules\Marketing\Models\Advertisement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdvertisementResource extends Resource
{
    protected static ?string $model = Advertisement::class;

    protected static ?string $slug = 'reklamlar';

    protected static ?string $modelLabel = 'Reklam';

    protected static ?string $pluralModelLabel = 'Reklamlar';

    protected static ?string $navigationLabel = 'Reklam Alanları';

    protected static string|\UnitEnum|null $navigationGroup = 'Pazarlama';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    public static function form(Schema $schema): Schema
    {
        return AdvertisementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdvertisementsTable::configure($table);
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
            'index' => ListAdvertisements::route('/'),
            'create' => CreateAdvertisement::route('/create'),
            'edit' => EditAdvertisement::route('/{record}/edit'),
        ];
    }
}
