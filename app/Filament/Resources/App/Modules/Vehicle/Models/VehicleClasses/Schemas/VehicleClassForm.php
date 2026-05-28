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

            TextInput::make('boarding_fee_trusted')
                ->label('İndi-Bindi · Güvenilir Müşteri (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->default(99.00)
                ->required()
                ->helperText('5+ tamamlanmış yolculuk, güven skoru ≥ 70'),

            TextInput::make('boarding_fee_standard')
                ->label('İndi-Bindi · Standart Müşteri (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->default(150.00)
                ->required()
                ->helperText('Telefon doğrulanmış, en az 1 tamamlanmış yolculuk'),

            TextInput::make('boarding_fee_new')
                ->label('İndi-Bindi · Yeni / Doğrulanmamış (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->default(210.00)
                ->required()
                ->helperText('İlk kez gelen veya geçmişi olmayan müşteri'),

            TextInput::make('boarding_fee_suspicious')
                ->label('İndi-Bindi · Şüpheli / Riskli (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->default(350.00)
                ->required()
                ->helperText('Geçmişte no-show, kara liste veya güven skoru < 25'),

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
