<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\NoShowReports;

use App\Filament\Resources\App\Modules\Booking\Models\NoShowReports\Pages\ListNoShowReports;
use App\Filament\Resources\App\Modules\Booking\Models\NoShowReports\Tables\NoShowReportsTable;
use App\Modules\Booking\Models\NoShowReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NoShowReportResource extends Resource
{
    protected static ?string $model = NoShowReport::class;

    protected static ?string $slug = 'no-show-reports';

    protected static ?string $modelLabel = 'No-Show Raporu';

    protected static ?string $pluralModelLabel = 'No-Show Raporları';

    protected static ?string $navigationLabel = 'No-Show Raporları';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function getNavigationBadge(): ?string
    {
        $c = NoShowReport::where('resolution', 'pending_review')->count();
        return $c > 0 ? (string) $c : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return NoShowReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNoShowReports::route('/'),
        ];
    }
}
