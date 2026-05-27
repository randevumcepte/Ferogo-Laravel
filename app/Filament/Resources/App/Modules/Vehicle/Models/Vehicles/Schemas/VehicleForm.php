<?php

namespace App\Filament\Resources\App\Modules\Vehicle\Models\Vehicles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_class_id')
                ->label('Sınıf')
                ->relationship('vehicleClass', 'name')
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('brand')
                ->label('Marka')
                ->required()
                ->maxLength(255)
                ->placeholder('Mercedes, Volkswagen, ...'),

            TextInput::make('model')
                ->label('Model')
                ->required()
                ->maxLength(255)
                ->placeholder('Vito, Transporter, ...'),

            TextInput::make('year_of_manufacture')
                ->label('Üretim Yılı')
                ->numeric()
                ->minValue(2010)
                ->maxValue((int) date('Y') + 1)
                ->required(),

            TextInput::make('color')
                ->label('Renk')
                ->required()
                ->maxLength(50),

            TextInput::make('plate')
                ->label('Plaka')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->placeholder('35 ABC 123'),

            TextInput::make('insurance_policy')
                ->label('Sigorta Poliçe No')
                ->maxLength(255),

            DatePicker::make('insurance_expires_at')
                ->label('Sigorta Bitiş')
                ->native(false)
                ->displayFormat('d.m.Y'),

            DatePicker::make('inspection_expires_at')
                ->label('Muayene Bitiş')
                ->native(false)
                ->displayFormat('d.m.Y'),

            DatePicker::make('license_expires_at')
                ->label('Ruhsat Bitiş')
                ->native(false)
                ->displayFormat('d.m.Y'),

            Toggle::make('has_baby_seat')->label('Bebek Koltuğu Var')->inline(false),
            Toggle::make('has_child_seat')->label('Çocuk Koltuğu Var')->inline(false),
            Toggle::make('has_booster_seat')->label('Yükseltici Var')->inline(false),
            Toggle::make('pet_friendly')->label('Evcil Hayvan Kabul')->inline(false),

            Select::make('status')
                ->label('Durum')
                ->options([
                    'pending' => 'Beklemede',
                    'active' => 'Aktif',
                    'suspended' => 'Askıda',
                    'retired' => 'Hizmet Dışı',
                ])
                ->required()
                ->default('pending'),
        ]);
    }
}
