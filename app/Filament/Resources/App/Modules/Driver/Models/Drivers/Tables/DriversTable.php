<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\Drivers\Tables;

use App\Modules\Driver\Models\Driver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('user.name')
                    ->label('Sürücü')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.phone')
                    ->label('Telefon')
                    ->copyable(),

                TextColumn::make('city.name')
                    ->label('Şehir')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('availability_status')
                    ->label('Müsaitlik')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'busy' => 'warning',
                        'offline' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'Müsait',
                        'busy' => 'Yolculukta',
                        'offline' => 'Çevrimdışı',
                        default => $state,
                    }),

                TextColumn::make('approval_status')
                    ->label('Onay')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Onaylı',
                        'pending' => 'Beklemede',
                        'rejected' => 'Reddedildi',
                        'suspended' => 'Askıya',
                        default => $state,
                    }),

                TextColumn::make('rating')
                    ->label('Puan')
                    ->formatStateUsing(fn ($state) => $state . ' ★')
                    ->color(fn ($state) => $state >= 4.5 ? 'success' : ($state >= 3.5 ? 'warning' : 'danger')),

                TextColumn::make('total_rides')
                    ->label('Toplam Yolculuk')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Komisyon')
                    ->suffix('%')
                    ->toggleable(),

                TextColumn::make('src_expires_at')
                    ->label('SRC Bitiş')
                    ->date('d.m.Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('availability_status')
                    ->label('Müsaitlik')
                    ->options([
                        'offline' => 'Çevrimdışı',
                        'online' => 'Müsait',
                        'busy' => 'Yolculukta',
                    ]),
                SelectFilter::make('approval_status')
                    ->label('Onay Durumu')
                    ->options([
                        'pending' => 'Beklemede',
                        'approved' => 'Onaylı',
                        'rejected' => 'Reddedildi',
                        'suspended' => 'Askıya',
                    ]),
                SelectFilter::make('city_id')
                    ->label('Şehir')
                    ->relationship('city', 'name'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('approve_documents')
                        ->label('Tüm belgeleri onayla')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Yüklenmiş belgeleri onayla')
                        ->modalDescription('Bu sürücünün yüklediği tüm belgeler "onaylı" olarak işaretlenecek.')
                        ->action(function (Driver $d) {
                            $now = now();
                            $update = [];
                            $cols = [
                                'license_file_path'         => 'license_approved_at',
                                'src_file_path'             => 'src_approved_at',
                                'psychotechnic_file_path'   => 'psychotechnic_approved_at',
                                'criminal_record_file_path' => 'criminal_record_approved_at',
                                'insurance_file_path'       => 'insurance_approved_at',
                                'inspection_file_path'      => 'inspection_approved_at',
                            ];
                            foreach ($cols as $fileCol => $approvedCol) {
                                if ($d->{$fileCol} && empty($d->{$approvedCol})) {
                                    $update[$approvedCol] = $now;
                                }
                            }
                            if (! empty($update)) {
                                $d->update($update);
                                Notification::make()->success()->title(count($update) . ' belge onaylandı')->send();
                            } else {
                                Notification::make()->info()->title('Onaylanacak belge yok')->send();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
