<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Categories\Tables;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverCategory;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriverCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->width('60px'),

                TextColumn::make('emoji')
                    ->label('')
                    ->size('lg')
                    ->extraAttributes(['style' => 'font-size: 24px;']),

                TextColumn::make('name')
                    ->label('Kategori')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('required_license_class')
                    ->label('Ehliyet')
                    ->badge()
                    ->color('info'),

                IconColumn::make('requires_src')
                    ->label('SRC')
                    ->boolean(),

                IconColumn::make('requires_helmet')
                    ->label('Kask')
                    ->boolean(),

                TextColumn::make('required_documents')
                    ->label('Belge sayısı')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->suffix(' belge'),

                TextColumn::make('drivers_count')
                    ->label('Sürücü sayısı')
                    ->getStateUsing(fn (DriverCategory $c) => Driver::where('driver_category_id', $c->id)->count())
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('sort_order');
    }
}
