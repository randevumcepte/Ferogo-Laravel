<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\RideRequests;

use App\Filament\Resources\App\Modules\Booking\Models\RideRequests\Pages\ListRideRequests;
use App\Filament\Resources\App\Modules\Booking\Models\RideRequests\Tables\RideRequestsTable;
use App\Modules\Booking\Models\RideRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RideRequestResource extends Resource
{
    protected static ?string $model = RideRequest::class;

    protected static ?string $slug = 'ride-requests';

    protected static ?string $modelLabel = 'Yolculuk Talebi';

    protected static ?string $pluralModelLabel = 'Yolculuk Talepleri';

    protected static ?string $navigationLabel = 'Talep Havuzu';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 15;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    public static function getNavigationBadge(): ?string
    {
        $c = RideRequest::where('status', 'pending')->where('offer_expires_at', '>', now())->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function table(Table $table): Table
    {
        return RideRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRideRequests::route('/'),
        ];
    }
}
