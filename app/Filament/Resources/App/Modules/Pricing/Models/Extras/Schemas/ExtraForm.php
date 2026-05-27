<?php

namespace App\Filament\Resources\App\Modules\Pricing\Models\Extras\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExtraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Ad')
                ->required()
                ->maxLength(255)
                ->placeholder('Bebek Koltuğu, Evcil Hayvan, ...'),

            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->label('Tip')
                ->options([
                    'seat' => 'Koltuk',
                    'pet' => 'Evcil Hayvan',
                    'package' => 'Paket',
                    'baggage' => 'Bagaj',
                    'other' => 'Diğer',
                ])
                ->required(),

            Textarea::make('description')
                ->label('Açıklama')
                ->rows(2)
                ->maxLength(500),

            TextInput::make('price')
                ->label('Fiyat (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->required(),

            Toggle::make('per_unit')
                ->label('Adet Başına Çarpılır')
                ->helperText('Açıksa: kullanıcı 2 koltuk seçerse 2× fiyat'),

            TextInput::make('max_quantity')
                ->label('Maks. Adet')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(10),

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
