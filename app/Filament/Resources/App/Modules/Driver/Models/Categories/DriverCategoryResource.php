<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Categories;

use App\Filament\Resources\App\Modules\Driver\Models\Categories\Pages\ListDriverCategories;
use App\Filament\Resources\App\Modules\Driver\Models\Categories\Tables\DriverCategoriesTable;
use App\Modules\Driver\Models\DriverCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverCategoryResource extends Resource
{
    protected static ?string $model = DriverCategory::class;

    protected static ?string $slug = 'driver-categories';

    protected static ?string $modelLabel = 'Sürücü Kategorisi';

    protected static ?string $pluralModelLabel = 'Sürücü Kategorileri';

    protected static ?string $navigationLabel = 'Sürücü Kategorileri';

    protected static string|\UnitEnum|null $navigationGroup = 'Filo';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    public static function table(Table $table): Table
    {
        return DriverCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverCategories::route('/'),
        ];
    }
}
