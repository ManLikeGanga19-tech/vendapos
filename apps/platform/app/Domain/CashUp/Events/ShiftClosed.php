<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A till's drawer shift has closed and been committed with its outbox row.
 * Listeners run as queued jobs carrying the event ID, so a rerun is a no-op.
 */
final class ShiftClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly string $shiftId,
    ) {}
}
