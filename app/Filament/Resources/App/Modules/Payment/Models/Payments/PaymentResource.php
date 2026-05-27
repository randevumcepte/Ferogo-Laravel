<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payments;

use App\Filament\Resources\App\Modules\Payment\Models\Payments\Pages\CreatePayment;
use App\Filament\Resources\App\Modules\Payment\Models\Payments\Pages\EditPayment;
use App\Filament\Resources\App\Modules\Payment\Models\Payments\Pages\ListPayments;
use App\Filament\Resources\App\Modules\Payment\Models\Payments\Schemas\PaymentForm;
use App\Filament\Resources\App\Modules\Payment\Models\Payments\Tables\PaymentsTable;
use App\Modules\Payment\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'payments';

    protected static ?string $modelLabel = 'Ödeme';

    protected static ?string $pluralModelLabel = 'Ödemeler';

    protected static ?string $navigationLabel = 'Ödemeler';

    protected static string|\UnitEnum|null $navigationGroup = 'Finans';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}
