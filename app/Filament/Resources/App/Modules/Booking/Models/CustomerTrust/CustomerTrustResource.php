<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\CustomerTrust;

use App\Filament\Resources\App\Modules\Booking\Models\CustomerTrust\Pages\ListCustomerTrust;
use App\Filament\Resources\App\Modules\Booking\Models\CustomerTrust\Tables\CustomerTrustTable;
use App\Modules\Booking\Models\CustomerTrust as CustomerTrustModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerTrustResource extends Resource
{
    protected static ?string $model = CustomerTrustModel::class;

    protected static ?string $slug = 'customer-trust';

    protected static ?string $modelLabel = 'Müşteri Güven Skoru';

    protected static ?string $pluralModelLabel = 'Müşteri Güven Skorları';

    protected static ?string $navigationLabel = 'Güven Skorları';

    protected static string|\UnitEnum|null $navigationGroup = 'Müşteri';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getNavigationBadge(): ?string
    {
        $c = CustomerTrustModel::where('is_blacklisted', true)->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return CustomerTrustTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerTrust::route('/'),
        ];
    }
}
