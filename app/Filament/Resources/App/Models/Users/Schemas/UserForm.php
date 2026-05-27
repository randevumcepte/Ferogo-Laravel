<?php

namespace App\Filament\Resources\App\Models\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Ad Soyad')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('E-posta')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Telefon')
                ->tel()
                ->maxLength(20),

            TextInput::make('tc_no')
                ->label('T.C. Kimlik No')
                ->numeric()
                ->minLength(11)
                ->maxLength(11),

            DatePicker::make('birth_date')
                ->label('Doğum Tarihi')
                ->native(false)
                ->displayFormat('d.m.Y'),

            Select::make('gender')
                ->label('Cinsiyet')
                ->options([
                    'male' => 'Erkek',
                    'female' => 'Kadın',
                    'other' => 'Diğer',
                ]),

            Select::make('type')
                ->label('Tip')
                ->options([
                    'admin' => 'Yönetici',
                    'driver' => 'Sürücü',
                    'customer' => 'Müşteri',
                ])
                ->required()
                ->default('customer'),

            Select::make('status')
                ->label('Durum')
                ->options([
                    'active' => 'Aktif',
                    'suspended' => 'Askıda',
                    'pending' => 'Beklemede',
                ])
                ->required()
                ->default('active'),

            TextInput::make('password')
                ->label('Şifre')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state))
                ->maxLength(255),
        ]);
    }
}
