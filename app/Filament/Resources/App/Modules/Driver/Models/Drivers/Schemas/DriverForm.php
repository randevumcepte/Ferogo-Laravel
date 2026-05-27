<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Kullanıcı')
                ->relationship('user', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->helperText('Önce Sistem → Kullanıcılar bölümünden type=driver olan bir kullanıcı oluşturun'),

            Select::make('city_id')
                ->label('Şehir')
                ->relationship('city', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('current_vehicle_id')
                ->label('Aktif Araç')
                ->relationship('currentVehicle', 'plate')
                ->searchable()
                ->preload(),

            TextInput::make('license_class')
                ->label('Ehliyet Sınıfı')
                ->default('B')
                ->maxLength(10),

            DatePicker::make('license_issued_at')
                ->label('Ehliyet Veriliş')
                ->native(false)
                ->displayFormat('d.m.Y'),

            DatePicker::make('license_expires_at')
                ->label('Ehliyet Bitiş')
                ->native(false)
                ->displayFormat('d.m.Y'),

            TextInput::make('src_certificate_number')
                ->label('SRC Belge No')
                ->maxLength(255),

            DatePicker::make('src_expires_at')
                ->label('SRC Bitiş')
                ->native(false)
                ->displayFormat('d.m.Y'),

            DatePicker::make('psychotechnic_test_at')
                ->label('Psikoteknik Tarihi')
                ->native(false)
                ->displayFormat('d.m.Y'),

            DatePicker::make('criminal_record_at')
                ->label('Sabıka Belgesi Tarihi')
                ->native(false)
                ->displayFormat('d.m.Y'),

            Select::make('experience_band')
                ->label('Deneyim')
                ->options([
                    'under_1' => '1 yıldan az',
                    '1_to_3' => '1–3 yıl',
                    '3_to_5' => '3–5 yıl',
                    '5_plus' => '5 yıl ve üzeri',
                ])
                ->default('1_to_3'),

            TextInput::make('commission_rate')
                ->label('Platform Komisyonu (%)')
                ->numeric()
                ->step(0.01)
                ->default(15.00)
                ->suffix('%')
                ->helperText('Platform kesintisi. Sürücüye kalan = 100 − bu değer'),

            Select::make('availability_status')
                ->label('Müsaitlik')
                ->options([
                    'offline' => 'Çevrimdışı',
                    'online' => 'Müsait',
                    'busy' => 'Yolculukta',
                ])
                ->default('offline'),

            Select::make('approval_status')
                ->label('Onay Durumu')
                ->options([
                    'pending' => 'Beklemede',
                    'approved' => 'Onaylı',
                    'rejected' => 'Reddedildi',
                    'suspended' => 'Askıya Alındı',
                ])
                ->default('pending')
                ->required()
                ->live(),

            Textarea::make('rejection_reason')
                ->label('Red Sebebi')
                ->rows(2)
                ->visible(fn ($get) => in_array($get('approval_status'), ['rejected', 'suspended'])),
        ]);
    }
}
