<?php

namespace App\Filament\Resources\App\Modules\Notification\Models\Campaigns\Schemas;

use App\Modules\Notification\Models\NotificationCampaign;
use App\Modules\Shared\Models\City;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NotificationCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // ─── İçerik ───
            Select::make('type')
                ->label('Tür')
                ->options(NotificationCampaign::TYPES)
                ->default('announcement')
                ->required(),

            TextInput::make('title')
                ->label('Başlık')
                ->required()
                ->maxLength(120)
                ->placeholder('Bu hafta sonu %20 indirim! 🎉'),

            Textarea::make('body')
                ->label('Mesaj')
                ->required()
                ->rows(3)
                ->maxLength(500)
                ->placeholder('Cuma–Pazar arası tüm yolculuklarda geçerli. Detaylar uygulamada.'),

            FileUpload::make('image_url')
                ->label('Görsel (opsiyonel)')
                ->image()
                ->disk('ads')
                ->directory('notifications')
                ->visibility('public')
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1200')
                ->imageResizeTargetHeight('1200')
                ->maxSize(8192)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->helperText('Push bildiriminde ve uygulama-içi popup\'ta gösterilir. Boş bırakılabilir.'),

            TextInput::make('deep_link')
                ->label('Yönlendirme (deep-link, opsiyonel)')
                ->maxLength(255)
                ->placeholder('/campaigns/hafta-sonu')
                ->helperText('Kullanıcı bildirime dokununca uygulamada gidilecek ekran/adres.'),

            Toggle::make('show_as_popup')
                ->label('Uygulama açılışında popup göster')
                ->helperText('AÇIK: kullanıcı uygulamayı açtığında bu mesaj tam ekran popup olarak da çıkar. KAPALI: sadece bildirim.')
                ->default(false),

            // ─── Hedefleme ───
            Select::make('audience')
                ->label('Kime gönderilecek?')
                ->options(NotificationCampaign::AUDIENCES)
                ->default('all')
                ->required()
                ->live(),

            Select::make('target.city_id')
                ->label('Şehir (sürücü hedefleme)')
                ->options(fn () => City::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                ->searchable()
                ->placeholder('Tüm şehirler')
                ->helperText('Yalnızca sürücüler için geçerli — seçili şehirdeki sürücülere gider.')
                ->visible(fn ($get) => $get('audience') !== 'customers'),

            Toggle::make('target.women_only')
                ->label('Sadece "kadın yolcu" modundaki sürücüler')
                ->default(false)
                ->visible(fn ($get) => $get('audience') !== 'customers'),

            Toggle::make('target.active_package')
                ->label('Sadece aktif paketi olan sürücüler')
                ->default(false)
                ->visible(fn ($get) => $get('audience') !== 'customers'),

            Select::make('target.trust_tiers')
                ->label('Müşteri güven kademesi')
                ->multiple()
                ->options([
                    'trusted'    => 'Güvenilir (5+ yolculuk)',
                    'standard'   => 'Standart',
                    'new'        => 'Yeni hesap',
                    'suspicious' => 'Riskli / no-show geçmişli',
                ])
                ->placeholder('Tüm müşteriler')
                ->helperText('Yalnızca müşteriler için geçerli. Boş = tüm müşteriler.')
                ->visible(fn ($get) => $get('audience') !== 'drivers'),

            Textarea::make('target.phones')
                ->label('Tekil telefon(lar) (opsiyonel)')
                ->rows(2)
                ->placeholder("05XX XXX XX XX\nHer satıra ya da virgülle bir numara")
                ->helperText('Sadece belirli kişilere göndermek için telefon yaz. Doldurulursa üstteki filtreler yok sayılır.')
                // Kaydederken metni diziye çevir (virgül/boşluk/satır ayır)
                ->dehydrateStateUsing(fn ($state) => collect(preg_split('/[\s,;]+/', (string) $state))
                    ->map(fn ($p) => trim($p))->filter()->values()->all())
                // Düzenlerken diziyi tekrar metne çevir
                ->afterStateHydrated(function ($component, $state) {
                    if (is_array($state)) {
                        $component->state(implode("\n", $state));
                    }
                }),

            // ─── Zamanlama ───
            DateTimePicker::make('scheduled_at')
                ->label('Zamanla (opsiyonel)')
                ->helperText('İleri bir tarih seçersen o an otomatik gönderilir. Boş bırakırsan taslak kalır — listeden "Şimdi Gönder" ile yollarsın.')
                ->minDate(now()),
        ]);
    }
}
