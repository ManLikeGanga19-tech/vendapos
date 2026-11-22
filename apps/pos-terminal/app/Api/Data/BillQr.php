<?php

declare(strict_types=1);

namespace App\Api\Data;

/**
 * What a bill prints to be paid by M-Pesa: Safaricom's QR image, and the
 * paybill and account number for customers who type them instead.
 */
final readonly class BillQr
{
    public function __construct(
        // Base64 PNG; null when Safaricom could not make one (the numbers still print).
        public ?string $png,
        public ?string $paybill,
        public ?string $account,
        public int $amountCents,
    ) {}

    public function canBeTyped(): bool
    {
        return $this->paybill !== null && $this->paybill !== '' && $this->account !== null && $this->account !== '';
    }
}
