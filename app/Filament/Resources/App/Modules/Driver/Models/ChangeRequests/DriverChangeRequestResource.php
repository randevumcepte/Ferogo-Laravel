<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\ChangeRequests;

use App\Filament\Resources\App\Modules\Driver\Models\ChangeRequests\Pages\ListDriverChangeRequests;
use App\Filament\Resources\App\Modules\Driver\Models\ChangeRequests\Tables\DriverChangeRequestsTable;
use App\Modules\Driver\Models\DriverChangeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverChangeRequestResource extends Resource
{
    protected static ?string $model = DriverChangeRequest::class;

    protected static ?string $slug = 'driver-change-requests';

    protected static ?string $modelLabel = 'Sürücü Değişiklik Talebi';

    protected static ?string $pluralModelLabel = 'Sürücü Değişiklik Talepleri';

    protected static ?string $navigationLabel = 'Sürücü Onay Kuyruğu';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return DriverChangeRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverChangeRequests::route('/'),
        ];
    }
}
