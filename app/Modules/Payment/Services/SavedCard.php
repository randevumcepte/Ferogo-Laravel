<?php

namespace App\Modules\Payment\Services;

/**
 * iyzico'dan dönen saklı kart bilgisinin sade DTO'su.
 */
final class SavedCard
{
    public function __construct(
        public readonly string $token,           // cardToken — ödeme yaparken kullanılır
        public readonly ?string $alias = null,   // örn. "İş Bankası Bonus"
        public readonly ?string $lastFour = null,
        public readonly ?string $bankName = null,
        public readonly ?string $cardFamily = null, // örn. "Bonus", "Maximum"
        public readonly ?string $cardAssociation = null, // VISA, MASTER_CARD
    ) {}

    public static function fromIyzico(array $row): self
    {
        return new self(
            token:           (string) ($row['cardToken'] ?? ''),
            alias:           $row['cardAlias'] ?? null,
            lastFour:        $row['lastFourDigits'] ?? null,
            bankName:        $row['cardBankName'] ?? null,
            cardFamily:      $row['cardFamily'] ?? null,
            cardAssociation: $row['cardAssociation'] ?? null,
        );
    }

    public function displayLabel(): string
    {
        $parts = [];
        if ($this->bankName) $parts[] = $this->bankName;
        if ($this->cardFamily) $parts[] = $this->cardFamily;
        if (empty($parts) && $this->alias) $parts[] = $this->alias;
        $name = empty($parts) ? 'Kart' : implode(' ', $parts);
        return $this->lastFour ? "{$name} •••• {$this->lastFour}" : $name;
    }
}
