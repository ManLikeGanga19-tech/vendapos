<?php

declare(strict_types=1);

namespace App\Domain\Etims\Data;

/** What an eTIMS client reports for one document. */
final readonly class EtimsResult
{
    /** @param array<string, mixed>|null $receipt */
    private function __construct(
        public bool $accepted,
        public ?array $receipt,
        public ?string $errorCode,
        public ?string $errorMessage,
    ) {}

    /** @param array<string, mixed> $receipt what KRA returned, e.g. FDN, QR and invoice ID */
    public static function accepted(array $receipt): self
    {
        return new self(true, $receipt, null, null);
    }

    public static function rejected(string $code, string $message): self
    {
        return new self(false, null, $code, $message);
    }
}
