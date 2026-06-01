<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LegalTextVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('key')
                ->label('Metin Anahtarı')
                ->options([
                    'platform_notice'     => 'Yasal Platform Bildirimi (Modal)',
                    'terms'               => 'Hizmet Şartları',
                    'kvkk'                => 'KVKK Aydınlatma Metni',
                    'distance_sales'      => 'Mesafeli Satış Sözleşmesi',
                    'cookies'             => 'Çerez Politikası',
                    'ride_sharing'        => 'Paylaşımlı Yolculuk Modeli',
                    'driver_registration' => 'Sürücü Kayıt Onayı',
                    'reservation_kvkk'    => 'Rezervasyon KVKK Onayı',
                ])
                ->required()
                ->searchable(),

            TextInput::make('version')
                ->label('Versiyon')
                ->required()
                ->maxLength(64)
                ->placeholder('örn. v1.1-2026-07-15'),

            TextInput::make('title')
                ->label('Başlık (admin notu)')
                ->maxLength(255),

            DateTimePicker::make('published_at')
                ->label('Yayın Tarihi')
                ->default(now())
                ->required(),

            DateTimePicker::make('superseded_at')
                ->label('Pasifleşme Tarihi')
                ->helperText('Yeni versiyon yayınlandığında doldur. Aktif metinler için boş bırak.'),

            Textarea::make('content')
                ->label('İçerik (kanonik metin)')
                ->required()
                ->rows(15)
                ->columnSpanFull()
                ->helperText('Bu metin kullanıcıya gösterilen orijinal halidir. Hash bu içerikten hesaplanır.'),

            TextInput::make('sha256')
                ->label('SHA-256')
                ->disabled()
                ->dehydrated()
                ->helperText('Kaydederken otomatik hesaplanır.')
                ->placeholder('Otomatik dolar'),

            Textarea::make('change_notes')
                ->label('Değişiklik Notları')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }
}
