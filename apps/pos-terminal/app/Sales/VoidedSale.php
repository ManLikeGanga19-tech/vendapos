<?php

declare(strict_types=1);

namespace App\Sales;

use Carbon\CarbonImmutable;

/** What the VOID slip and the screen show once a sale is voided: what was sold, and what to give back. */
final readonly class VoidedSale
{
    /** @param  list<array{name: string, quantity: int, line_total_cents: int}>  $lines */
    public function __construct(
        public ?string $receiptNumber,
        public CarbonImmutable $placedAt,
        public CarbonImmutable $voidedAt,
        public array $lines,
        public int $totalCents,
        // Handed back over the counter now.
        public int $cashCents,
        // Sent back by the owner later: the till cannot reverse M-Pesa.
        public int $mpesaCents,
        public string $reason,
        public ?string $note,
        public string $voidedBy,
        public string $approvedBy,
    ) {}
}
