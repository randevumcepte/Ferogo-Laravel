<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\NoShowReports\Pages;

use App\Filament\Resources\App\Modules\Booking\Models\NoShowReports\NoShowReportResource;
use Filament\Resources\Pages\ListRecords;

class ListNoShowReports extends ListRecords
{
    protected static string $resource = NoShowReportResource::class;
}
