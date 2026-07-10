<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\Pages;

use App\Filament\Resources\App\Modules\Legal\Models\LegalConsents\LegalConsentResource;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewLegalConsent extends ViewRecord
{
    protected static string $resource = LegalConsentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Onay Bilgileri')
                ->schema([
                    TextEntry::make('id')->label('Kayıt ID'),
                    TextEntry::make('consent_type')->label('Onay Türü')->badge(),
                    TextEntry::make('accepted_via')->label('Kanal')->badge(),
                    TextEntry::make('accepted_at')->label('Kabul Tarihi')->dateTime('d.m.Y H:i:s'),
                    TextEntry::make('version_snapshot')->label('Metin Versiyonu')->copyable(),
                    TextEntry::make('sha256_snapshot')->label('SHA-256 (içerik hash\'i)')->copyable(),
                ])
                ->columns(2),

            Section::make('Kullanıcı / Kimlik')
                ->schema([
                    TextEntry::make('user.name')->label('Kullanıcı')->placeholder('— anonim —'),
                    TextEntry::make('phone')->label('Telefon')->placeholder('— bilinmiyor —')->copyable(),
                    TextEntry::make('session_id')->label('Session ID')->copyable(),
                    TextEntry::make('device_fingerprint')->label('Cihaz Parmak İzi')->copyable(),
                ])
                ->columns(2),

            Section::make('Forensik / Bağlam')
                ->schema([
                    TextEntry::make('ip_address')->label('IP Adresi')->copyable(),
                    TextEntry::make('user_agent')->label('Tarayıcı (User-Agent)')->columnSpanFull(),
                    TextEntry::make('request_url')->label('Kabul edildiği sayfa')->columnSpanFull(),
                    TextEntry::make('referer')->label('Önceki sayfa')->columnSpanFull(),
                    TextEntry::make('locale')->label('Dil'),
                ])
                ->columns(2),

            Section::make('Kabul Edilen Metnin Tam İçeriği (Mahkeme Delili)')
                ->schema([
                    TextEntry::make('textVersion.content')
                        ->label('')
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'whitespace-pre-wrap text-sm']),
                ])
                ->collapsed(),

            Section::make('Ham Payload')
                ->schema([
                    TextEntry::make('raw_payload')
                        ->label('Ek veri')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state),
                ])
                ->collapsed(),
        ]);
    }
}
