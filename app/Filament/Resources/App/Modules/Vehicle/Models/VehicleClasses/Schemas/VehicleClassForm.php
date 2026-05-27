<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\VehicleClasses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Sınıf Adı')
                ->required()
                ->maxLength(255)
                ->placeholder('Easy / Platinum / VIP'),

            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Açıklama')
                ->rows(2)
                ->maxLength(500),

            TextInput::make('max_passengers')
                ->label('Maks. Yolcu')
                ->numeric()
                ->default(4)
                ->required(),

            TextInput::make('max_luggage')
                ->label('Maks. Bagaj')
                ->numeric()
                ->default(3)
                ->required(),

            TextInput::make('base_fare')
                ->label('Açılış Ücreti (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->required(),

            TextInput::make('per_km_fare')
                ->label('Km Başı (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->required(),

            TextInput::make('per_minute_fare')
                ->label('Dakika Başı (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->required(),

            TextInput::make('minimum_fare')
                ->label('Minimum Ücret (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->required(),

            TextInput::make('sort_order')
                ->label('Sıra')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
