<?php

namespace App\Filament\Resources\App\Modules\Booking\Models\Rides\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('city_id')
                ->label('Şehir')
                ->relationship('city', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Select::make('vehicle_class_id')
                ->label('Araç Sınıfı')
                ->relationship('vehicleClass', 'name')
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('customer_name')
                ->label('Müşteri Adı')
                ->required()
                ->maxLength(255),

            TextInput::make('customer_phone')
                ->label('Müşteri Telefon')
                ->tel()
                ->required()
                ->maxLength(20),

            TextInput::make('customer_tc_no')
                ->label('T.C. Kimlik No')
                ->maxLength(11)
                ->minLength(11)
                ->numeric()
                ->helperText('5682 sayılı kanun + KVKK uyumu için zorunlu'),

            TextInput::make('pickup_address')
                ->label('Alış Adresi')
                ->required()
                ->maxLength(255),

            TextInput::make('pickup_lat')
                ->label('Alış Enlem')
                ->numeric()
                ->step(0.0000001)
                ->required(),

            TextInput::make('pickup_lng')
                ->label('Alış Boylam')
                ->numeric()
                ->step(0.0000001)
                ->required(),

            TextInput::make('dropoff_address')
                ->label('Bırakış Adresi')
                ->required()
                ->maxLength(255),

            TextInput::make('dropoff_lat')
                ->label('Bırakış Enlem')
                ->numeric()
                ->step(0.0000001)
                ->required(),

            TextInput::make('dropoff_lng')
                ->label('Bırakış Boylam')
                ->numeric()
                ->step(0.0000001)
                ->required(),

            DateTimePicker::make('scheduled_at')
                ->label('Planlanan Saat')
                ->native(false)
                ->displayFormat('d.m.Y H:i'),

            TextInput::make('passenger_count')
                ->label('Yolcu Sayısı')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(8),

            TextInput::make('luggage_count')
                ->label('Bagaj Sayısı')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(10),

            Select::make('driver_id')
                ->label('Atanan Sürücü')
                ->relationship(
                    name: 'driver',
                    titleAttribute: 'id',
                    modifyQueryUsing: fn ($query) => $query->with('user')->where('approval_status', 'approved'),
                )
                ->getOptionLabelFromRecordUsing(fn ($record) => ($record->user->name ?? 'Sürücü #' . $record->id) . ' · ' . ($record->city->name ?? '—'))
                ->searchable(['user.name', 'user.phone'])
                ->preload()
                ->placeholder('Onaylı sürücülerden seç')
                ->helperText('Sadece onaylı sürücüler listede görünür'),

            Select::make('status')
                ->label('Durum')
                ->options([
                    'draft' => 'Taslak',
                    'pending' => 'Beklemede',
                    'searching' => 'Sürücü Aranıyor',
                    'assigned' => 'Atandı',
                    'driver_arriving' => 'Sürücü Yolda',
                    'in_progress' => 'Yolculukta',
                    'completed' => 'Tamamlandı',
                    'cancelled' => 'İptal Edildi',
                    'no_show' => 'Müşteri Gelmedi',
                ])
                ->required()
                ->default('pending'),

            Select::make('source')
                ->label('Kaynak')
                ->options([
                    'web' => 'Web',
                    'app' => 'Mobil App',
                    'call' => 'Telefon',
                    'whatsapp' => 'WhatsApp',
                ])
                ->default('web'),

            TextInput::make('total_fare')
                ->label('Toplam Ücret (₺)')
                ->numeric()
                ->step(0.01)
                ->prefix('₺'),

            Textarea::make('pickup_notes')
                ->label('Alış Notları')
                ->rows(2),

            Textarea::make('dropoff_notes')
                ->label('Bırakış Notları')
                ->rows(2),
        ]);
    }
}
