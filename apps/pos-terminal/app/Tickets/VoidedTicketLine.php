<?php

declare(strict_types=1);

namespace App\Tickets;

use Carbon\CarbonImmutable;

/** What the VOID slip for the kitchen or bar says once a sent line comes off a ticket. */
final readonly class VoidedTicketLine
{
    public function __construct(
        public string $ticketId,
        // "Table 4", or the takeaway's name.
        public string $ticketTitle,
        public string $name,
        // How many came off, and how many of the line are still to make.
        public int $quantity,
        public int $left,
        public CarbonImmutable $voidedAt,
        public string $reason,
        public ?string $note,
        public string $voidedBy,
        public string $approvedBy,
    ) {}
}
