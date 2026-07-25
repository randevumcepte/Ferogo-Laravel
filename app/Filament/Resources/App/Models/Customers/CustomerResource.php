<?php

namespace App\Filament\Resources\App\Models\Customers;

use App\Filament\Resources\App\Models\Customers\Pages\EditCustomer;
use App\Filament\Resources\App\Models\Customers\Pages\ListCustomers;
use App\Filament\Resources\App\Models\Users\Schemas\UserForm;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Yolcular (müşteri hesapları) — Sistem > Kullanıcılar içinden ayrıştırılmış,
 * yalnızca type=customer olanları listeler. "Müşteri" menü grubunda.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'customers';

    protected static ?string $modelLabel = 'Yolcu';

    protected static ?string $pluralModelLabel = 'Yolcular';

    protected static ?string $navigationLabel = 'Yolcular';

    protected static string|\UnitEnum|null $navigationGroup = 'Müşteri';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    /** Yalnızca müşteri hesapları + yolculuk sayısı. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'customer')
            ->withCount('customerRides');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) User::where('type', 'customer')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),

                TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gender')
                    ->label('Cinsiyet')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male' => 'Erkek',
                        'female' => 'Kadın',
                        'other' => 'Diğer',
                        default => '—',
                    }),

                TextColumn::make('customer_rides_count')
                    ->label('Yolculuk')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'suspended' => 'Askıda',
                        'pending' => 'Beklemede',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'active' => 'Aktif',
                        'suspended' => 'Askıda',
                        'pending' => 'Beklemede',
                    ]),
                SelectFilter::make('gender')
                    ->label('Cinsiyet')
                    ->options([
                        'male' => 'Erkek',
                        'female' => 'Kadın',
                        'other' => 'Diğer',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
