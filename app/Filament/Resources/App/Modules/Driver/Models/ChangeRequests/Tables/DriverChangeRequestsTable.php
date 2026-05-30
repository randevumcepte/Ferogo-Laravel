<?php

namespace App\Filament\Resources\App\Modules\Driver\Models\ChangeRequests\Tables;

use App\Modules\Driver\Models\DriverChangeRequest;
use App\Modules\Vehicle\Models\VehicleClass;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DriverChangeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable()->width('60px'),

                TextColumn::make('driver.user.name')
                    ->label('Sürücü')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'vehicle' => 'Araç',
                        'profile_critical' => 'Profil',
                        default => $s,
                    }),

                TextColumn::make('payload')
                    ->label('Değişiklikler')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state)) return '—';
                        $labels = [
                            'vehicle_class_id'    => 'Sınıf',
                            'brand'               => 'Marka',
                            'model'               => 'Model',
                            'year_of_manufacture' => 'Yıl',
                            'color'               => 'Renk',
                            'plate'               => 'Plaka',
                            'add_photos'          => 'Yeni foto',
                            'remove_photos'       => 'Silinecek foto',
                        ];
                        $parts = [];
                        foreach ($state as $k => $v) {
                            $key = $labels[$k] ?? $k;
                            if (in_array($k, ['add_photos', 'remove_photos'], true)) {
                                $parts[] = $key . ': ' . count($v);
                            } elseif ($k === 'vehicle_class_id') {
                                $cls = VehicleClass::find($v);
                                $parts[] = $key . ': ' . ($cls?->name ?? $v);
                            } else {
                                $parts[] = $key . ': ' . $v;
                            }
                        }
                        return implode(' · ', $parts);
                    })
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 360px;']),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'pending'  => 'Beklemede',
                        'approved' => 'Onaylandı',
                        'rejected' => 'Reddedildi',
                        default    => $s,
                    }),

                TextColumn::make('created_at')
                    ->label('Talep')
                    ->since(),

                TextColumn::make('reviewed_at')
                    ->label('İncelendi')
                    ->since()
                    ->toggleable(),

                TextColumn::make('reviewer.name')
                    ->label('Onaylayan')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options([
                        'pending'  => 'Beklemede',
                        'approved' => 'Onaylandı',
                        'rejected' => 'Reddedildi',
                    ])
                    ->default('pending'),
                SelectFilter::make('type')
                    ->label('Tür')
                    ->options([
                        'vehicle'          => 'Araç',
                        'profile_critical' => 'Profil',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Onayla')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (DriverChangeRequest $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (DriverChangeRequest $r) {
                        self::applyApprovedChange($r);
                        $r->update([
                            'status'      => 'approved',
                            'reviewed_at' => now(),
                            'reviewed_by' => Auth::id(),
                        ]);
                        Notification::make()
                            ->success()
                            ->title('Onaylandı')
                            ->body('Değişiklikler canlıya alındı.')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reddet')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (DriverChangeRequest $r) => $r->status === 'pending')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Red gerekçesi')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (DriverChangeRequest $r, array $data) {
                        $r->update([
                            'status'           => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_at'      => now(),
                            'reviewed_by'      => Auth::id(),
                        ]);
                        Notification::make()
                            ->warning()
                            ->title('Reddedildi')
                            ->body('Sürücü değişiklik talebi reddedildi.')
                            ->send();
                    }),
            ]);
    }

    /** Onaylanan payload'u gerçek araca uygular. */
    private static function applyApprovedChange(DriverChangeRequest $r): void
    {
        if ($r->type !== 'vehicle') return;

        $driver = $r->driver()->with('currentVehicle')->first();
        if (! $driver || ! $driver->currentVehicle) return;

        $vehicle = $driver->currentVehicle;
        $payload = $r->payload;

        $directFields = ['vehicle_class_id', 'brand', 'model', 'year_of_manufacture', 'color', 'plate'];
        $updates = [];
        foreach ($directFields as $f) {
            if (isset($payload[$f])) $updates[$f] = $payload[$f];
        }

        // Foto güncellemeleri
        $photos = is_array($vehicle->photos) ? $vehicle->photos : [];
        if (! empty($payload['remove_photos'])) {
            foreach ($payload['remove_photos'] as $rm) {
                $photos = array_values(array_filter($photos, fn ($p) => $p !== $rm));
                if (! str_starts_with($rm, 'http')) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($rm);
                }
            }
        }
        if (! empty($payload['add_photos'])) {
            foreach ($payload['add_photos'] as $p) {
                if (! in_array($p, $photos, true)) $photos[] = $p;
            }
        }
        $photos = array_values(array_unique(array_slice($photos, 0, 20)));
        $updates['photos'] = $photos;

        if (! empty($updates)) $vehicle->update($updates);
    }
}
