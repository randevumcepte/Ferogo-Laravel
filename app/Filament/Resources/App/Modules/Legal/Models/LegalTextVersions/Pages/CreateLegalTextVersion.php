<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Pages;

use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\LegalTextVersionResource;
use App\Modules\Legal\Models\LegalTextVersion;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalTextVersion extends CreateRecord
{
    protected static string $resource = LegalTextVersionResource::class;

    /**
     * Yeni versiyon kaydedilirken:
     *  - SHA-256 hash içerikten otomatik hesaplanır
     *  - Aynı key'in mevcut aktif versiyonu varsa superseded_at set edilir
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sha256'] = LegalTextVersion::hashContent((string) ($data['content'] ?? ''));
        return $data;
    }

    protected function afterCreate(): void
    {
        // Aynı key için diğer aktif versiyonları pasife al
        LegalTextVersion::query()
            ->where('key', $this->record->key)
            ->where('id', '!=', $this->record->id)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);
    }
}
