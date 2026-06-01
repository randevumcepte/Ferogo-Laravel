<?php

namespace App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\Pages;

use App\Filament\Resources\App\Modules\Legal\Models\LegalTextVersions\LegalTextVersionResource;
use App\Modules\Legal\Models\LegalTextVersion;
use Filament\Resources\Pages\EditRecord;

class EditLegalTextVersion extends EditRecord
{
    protected static string $resource = LegalTextVersionResource::class;

    /**
     * Mevcut versiyonun içeriği değiştirilirse SHA-256 yeniden hesaplanır.
     * NOT: Üretimde bu davranış riskli olabilir (consent'ler eski hash'e bağlı).
     * İdealde "yeni versiyon ekle" akışı tercih edilir.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['content'])) {
            $data['sha256'] = LegalTextVersion::hashContent((string) $data['content']);
        }
        return $data;
    }
}
