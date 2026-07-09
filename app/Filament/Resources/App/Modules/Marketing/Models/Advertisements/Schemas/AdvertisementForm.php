<?php

namespace App\Filament\Resources\App\Modules\Marketing\Models\Advertisements\Schemas;

use App\Modules\Marketing\Models\Advertisement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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

            FileUpload::make('image_url')
                ->label('Görsel')
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
                ->helperText(fn ($get): string => '📐 Önerilen ölçü: ' . Advertisement::dimensionsFor($get('placement'))
                    . '. Bilgisayarından görsel seç — tarayıcı otomatik küçültür. JPG / PNG / WebP. '
                    . 'Boş bırakılırsa marka kartı (★) gösterilir.'),

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
                ->helperText('Tekellik reklamlarında ve boş alan sırasında küçük sıra önce gelir.'),

            TextInput::make('rotation_weight')
                ->label('Rotasyon Ağırlığı (gösterim payı)')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->helperText('Aynı alanda birden çok reklam dönerken bu reklamın gösterim payı. '
                    . '1 = normal. 3 = eşit ağırlıklı bir reklamın 3 katı sıklıkta çıkar. '
                    . 'Daha çok ödeyen sponsora daha yüksek pay verebilirsin.'),

            Toggle::make('is_exclusive')
                ->label('Tekellik (bu alanda TEK bu reklam)')
                ->helperText('AÇIK: bu reklam alanı yalnızca bu markaya aittir — rotasyon durur, '
                    . 'başka reklam bu alanda görünmez. Tekellik / Takeover / Ana Sponsor paketleri için. '
                    . 'KAPALI: reklam diğerleriyle rotasyonda döner (paylaşımlı).')
                ->default(false),

            Select::make('target_hours')
                ->label('Saat Hedefleme')
                ->multiple()
                ->options(collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00'])->all())
                ->helperText('Reklam yalnızca seçili saatlerde gösterilir. Boş = her saat.'),

            Select::make('target_days')
                ->label('Gün Hedefleme')
                ->multiple()
                ->options([
                    1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
                    5 => 'Cuma', 6 => 'Cumartesi', 0 => 'Pazar',
                ])
                ->helperText('Reklam yalnızca seçili günlerde gösterilir. Boş = her gün.'),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
