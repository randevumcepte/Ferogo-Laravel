<?php

namespace App\Filament\Resources\App\Modules\Shared\Models\Cities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Şehir Adı')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->helperText('URL içinde kullanılan kısa ad (örn. izmir)'),

            TextInput::make('country_code')
                ->label('Ülke Kodu')
                ->default('TR')
                ->maxLength(2),

            TextInput::make('center_lat')
                ->label('Merkez Enlem')
                ->numeric()
                ->step(0.0000001)
                ->placeholder('38.4192'),

            TextInput::make('center_lng')
                ->label('Merkez Boylam')
                ->numeric()
                ->step(0.0000001)
                ->placeholder('27.1287'),

            TextInput::make('timezone')
                ->label('Saat Dilimi')
                ->default('Europe/Istanbul')
                ->maxLength(64),

            TextInput::make('sort_order')
                ->label('Sıra')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Pasif şehirlerde rezervasyon yapılamaz'),
        ]);
    }
}
