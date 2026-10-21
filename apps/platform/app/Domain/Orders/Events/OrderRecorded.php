<?php

declare(strict_types=1);

namespace App\Domain\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new sale has been committed with its outbox row. Listeners (inventory,
 * reporting, eTIMS, receipts) run as queued jobs carrying the event ID, so a
 * rerun is a no-op. Never fires for a rolled-back transaction.
 */
final class OrderRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly string $orderId,
    ) {}
}
