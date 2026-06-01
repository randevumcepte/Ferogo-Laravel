<?php

namespace App\Modules\Payment\Services;

final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $reference = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function ok(string $reference, array $raw = []): self
    {
        return new self(true, $reference, null, $raw);
    }

    public static function fail(string $message, array $raw = []): self
    {
        return new self(false, null, $message, $raw);
    }
}
