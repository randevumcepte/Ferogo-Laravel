<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\PricingRules;

use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Pages\EditPricingRule;
use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Pages\ListPricingRules;
use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Schemas\PricingRuleForm;
use App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Tables\PricingRulesTable;
use App\Modules\Pricing\Models\PricingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static ?string $slug = 'pricing-rules';

    protected static ?string $modelLabel = 'Tarife';

    protected static ?string $pluralModelLabel = 'Tarifeler';

    protected static ?string $navigationLabel = 'Tarifeler';

    protected static string|\UnitEnum|null $navigationGroup = 'Konfigürasyon';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return PricingRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingRulesTable::configure($table);
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
            'index' => ListPricingRules::route('/'),
            'create' => CreatePricingRule::route('/create'),
            'edit' => EditPricingRule::route('/{record}/edit'),
        ];
    }
}
