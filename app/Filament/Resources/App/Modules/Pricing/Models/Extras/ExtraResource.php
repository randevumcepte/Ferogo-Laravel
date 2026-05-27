<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\Extras;

use App\Filament\Resources\App\Modules\Pricing\Models\Extras\Pages\CreateExtra;
use App\Filament\Resources\App\Modules\Pricing\Models\Extras\Pages\EditExtra;
use App\Filament\Resources\App\Modules\Pricing\Models\Extras\Pages\ListExtras;
use App\Filament\Resources\App\Modules\Pricing\Models\Extras\Schemas\ExtraForm;
use App\Filament\Resources\App\Modules\Pricing\Models\Extras\Tables\ExtrasTable;
use App\Modules\Pricing\Models\Extra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExtraResource extends Resource
{
    protected static ?string $model = Extra::class;

    protected static ?string $slug = 'extras';

    protected static ?string $modelLabel = 'Ekstra';

    protected static ?string $pluralModelLabel = 'Ekstralar';

    protected static ?string $navigationLabel = 'Ekstralar';

    protected static string|\UnitEnum|null $navigationGroup = 'Konfigürasyon';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    public static function form(Schema $schema): Schema
    {
        return ExtraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtrasTable::configure($table);
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
            'index' => ListExtras::route('/'),
            'create' => CreateExtra::route('/create'),
            'edit' => EditExtra::route('/{record}/edit'),
        ];
    }
}
