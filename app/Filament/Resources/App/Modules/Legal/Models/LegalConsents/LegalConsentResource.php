<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalConsents;

use App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\Pages\ListLegalConsents;
use App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\Pages\ViewLegalConsent;
use App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\Tables\LegalConsentsTable;
use App\Modules\Legal\Models\LegalConsent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LegalConsentResource extends Resource
{
    protected static ?string $model = LegalConsent::class;

    protected static ?string $slug = 'legal-consents';

    protected static ?string $modelLabel = 'Yasal Onay';

    protected static ?string $pluralModelLabel = 'Yasal Onaylar';

    protected static ?string $navigationLabel = 'Yasal Onaylar';

    protected static string|\UnitEnum|null $navigationGroup = 'Yasal';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function table(Table $table): Table
    {
        return LegalConsentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false; // Audit log — sadece sistem üretir
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Immutable — değiştirilemez
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Silinmez — legal retention
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalConsents::route('/'),
            'view'  => ViewLegalConsent::route('/{record}'),
        ];
    }
}
