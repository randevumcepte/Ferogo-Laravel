<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions;

use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Pages\CreateLegalTextVersion;
use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Pages\EditLegalTextVersion;
use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Pages\ListLegalTextVersions;
use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Schemas\LegalTextVersionForm;
use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Tables\LegalTextVersionsTable;
use App\Modules\Legal\Models\LegalTextVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LegalTextVersionResource extends Resource
{
    protected static ?string $model = LegalTextVersion::class;

    protected static ?string $slug = 'legal-text-versions';

    protected static ?string $modelLabel = 'Hukuki Metin Versiyonu';

    protected static ?string $pluralModelLabel = 'Hukuki Metin Versiyonları';

    protected static ?string $navigationLabel = 'Metin Versiyonları';

    protected static string|\UnitEnum|null $navigationGroup = 'Yasal';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return LegalTextVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalTextVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListLegalTextVersions::route('/'),
            'create' => CreateLegalTextVersion::route('/create'),
            'edit'   => EditLegalTextVersion::route('/{record}/edit'),
        ];
    }
}
