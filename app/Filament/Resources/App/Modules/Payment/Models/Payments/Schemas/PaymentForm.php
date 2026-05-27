<?php

namespace App\Filament\Resources\App\Modules\Payment\Models\Payments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ride_id')
                ->label('Rezervasyon')
                ->relationship('ride', 'id')
                ->required()
                ->searchable(),

            Select::make('user_id')
                ->label('Kullanıcı')
                ->relationship('user', 'name')
                ->required()
                ->searchable(),

            TextInput::make('amount')
                ->label('Tutar (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺')
                ->required(),

            TextInput::make('currency')
                ->label('Para Birimi')
                ->default('TRY')
                ->maxLength(3),

            Select::make('status')
                ->label('Durum')
                ->options([
                    'pending' => 'Beklemede',
                    'authorized' => 'Yetkilendirildi',
                    'captured' => 'Tahsil Edildi',
                    'failed' => 'Başarısız',
                    'refunded' => 'İade Edildi',
                    'cancelled' => 'İptal',
                ])
                ->required()
                ->default('pending'),

            Select::make('provider')
                ->label('Sağlayıcı')
                ->options([
                    'iyzico' => 'iyzico',
                    'cash' => 'Nakit',
                    'card_on_arrival' => 'Araçta Kart',
                ])
                ->default('iyzico')
                ->required(),

            TextInput::make('provider_payment_id')
                ->label('Sağlayıcı Ödeme ID')
                ->maxLength(255),

            TextInput::make('card_last_4')
                ->label('Kart Son 4')
                ->maxLength(4),

            TextInput::make('card_brand')
                ->label('Kart Markası')
                ->maxLength(32),

            Textarea::make('failure_reason')
                ->label('Başarısızlık Sebebi')
                ->rows(2),
        ]);
    }
}
