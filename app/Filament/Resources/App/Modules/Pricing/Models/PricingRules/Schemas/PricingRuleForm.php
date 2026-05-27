<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\PricingRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PricingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('city_id')
                ->label('Şehir')
                ->relationship('city', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('vehicle_class_id')
                ->label('Araç Sınıfı')
                ->relationship('vehicleClass', 'name')
                ->required()
                ->searchable()
                ->preload(),

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

            TextInput::make('night_multiplier')
                ->label('Gece Çarpanı')
                ->numeric()
                ->step(0.01)
                ->default(1.50)
                ->suffix('×')
                ->helperText('1.50 = %50 zam'),

            TimePicker::make('night_start')
                ->label('Gece Başlangıç')
                ->seconds(false)
                ->default('22:00'),

            TimePicker::make('night_end')
                ->label('Gece Bitiş')
                ->seconds(false)
                ->default('06:00'),

            TextInput::make('peak_multiplier')
                ->label('Yoğun Saat Çarpanı')
                ->numeric()
                ->step(0.01)
                ->default(1.25)
                ->suffix('×'),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
