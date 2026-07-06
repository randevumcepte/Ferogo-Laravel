<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Schemas;

use App\Modules\Marketing\Models\Advertisement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdvertisementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('placement')
                ->label('Reklam Alanı')
                ->options(Advertisement::PLACEMENTS)
                ->required()
                ->helperText('Uygulamada reklamın görüneceği yer. Her alanda aynı anda tek reklam yayınlanır.'),

            Select::make('sector')
                ->label('Sektör')
                ->options(Advertisement::SECTORS)
                ->native(false)
                ->searchable(),

            TextInput::make('title')
                ->label('Başlık')
                ->required()
                ->maxLength(255)
                ->placeholder('Kışa hazır mısın? %20 lastik indirimi'),

            TextInput::make('sponsor_name')
                ->label('Sponsor / Marka Adı')
                ->maxLength(255)
                ->placeholder('Örn: Ege Sigorta'),

            Textarea::make('description')
                ->label('Açıklama')
                ->rows(2)
                ->maxLength(500),

            TextInput::make('image_url')
                ->label('Görsel URL')
                ->url()
                ->maxLength(255)
                ->helperText('Boş bırakılırsa marka kartı olarak gösterilir.'),

            TextInput::make('link_url')
                ->label('Tıklanınca Gidilecek Adres')
                ->url()
                ->maxLength(255)
                ->placeholder('https://...'),

            TextInput::make('cta_text')
                ->label('Buton Yazısı (CTA)')
                ->maxLength(40)
                ->placeholder('Teklif Al'),

            DateTimePicker::make('starts_at')
                ->label('Yayın Başlangıcı')
                ->helperText('Boş = hemen'),

            DateTimePicker::make('ends_at')
                ->label('Yayın Bitişi')
                ->helperText('Boş = süresiz'),

            TextInput::make('sort_order')
                ->label('Sıra')
                ->numeric()
                ->default(0)
                ->helperText('Aynı alanda birden fazla reklam varsa küçük sıra önce yayınlanır.'),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
