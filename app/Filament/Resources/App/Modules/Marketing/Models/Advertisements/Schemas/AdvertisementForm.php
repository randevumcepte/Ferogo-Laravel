<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Schemas;

use App\Modules\Marketing\Models\Advertisement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
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
                ->live()
                ->helperText(fn ($get): string => $get('placement')
                    ? '📐 Bu alan için önerilen görsel ölçüsü: ' . Advertisement::dimensionsFor($get('placement'))
                    : 'Reklamın görüneceği yer. Seçince o alanın önerilen görsel ölçüsü burada yazacak.'),

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

            Radio::make('image_source')
                ->label('Görsel Kaynağı')
                ->options([
                    'upload' => 'Bilgisayardan dosya yükle',
                    'url'    => 'İnternet adresi (URL) yapıştır',
                ])
                ->default('upload')
                ->inline()
                ->inlineLabel(false)
                ->dehydrated(false) // sanal alan: veritabanına kaydedilmez
                ->live()
                ->afterStateHydrated(function (Radio $component, ?Advertisement $record) {
                    // Düzenleme: mevcut değer http ile başlıyorsa "URL", değilse "dosya"
                    $value = $record?->image_url;
                    $component->state(
                        $value && str_starts_with($value, 'http') ? 'url' : 'upload'
                    );
                }),

            FileUpload::make('image_url')
                ->label('Görsel (Dosya)')
                ->image()
                ->disk('ads')
                ->directory('ads')
                ->visibility('public')
                // Görseli tarayıcıda YÜKLEMEDEN ÖNCE küçült: dosya küçülür, sunucu/nginx
                // limitine takılmaz, "Boyut hesaplanıyor"da sonsuz dönme biter.
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1600')
                ->imageResizeTargetHeight('1600')
                ->maxSize(8192) // KB — küçültme sonrası zaten çok altında kalır
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->visible(fn ($get): bool => $get('image_source') === 'upload')
                ->helperText(fn ($get): string => '📐 Önerilen ölçü: ' . Advertisement::dimensionsFor($get('placement'))
                    . '. İstediğin boyutta yükleyebilirsin — tarayıcı otomatik küçültür. JPG / PNG / WebP. '
                    . 'Boş bırakılırsa marka kartı (★) gösterilir.'),

            TextInput::make('image_url')
                ->label('Görsel URL')
                ->url()
                ->maxLength(255)
                ->placeholder('https://... .jpg')
                ->visible(fn ($get): bool => $get('image_source') === 'url')
                ->helperText(fn ($get): string => '📐 Önerilen ölçü: ' . Advertisement::dimensionsFor($get('placement'))
                    . '. Boş bırakılırsa marka kartı (★) gösterilir.'),

            Toggle::make('image_only')
                ->label('Tam görsel (metin ve buton gösterme)')
                ->helperText('AÇIK (önerilen): yüklediğin görsel TÜM alanı kaplar, kırpılmaz, üstüne yazı/buton binmez '
                    . '(görselin kendisi tam reklamdır). KAPALI: solda görsel + sağda başlık/açıklama/buton.')
                ->default(true),

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
