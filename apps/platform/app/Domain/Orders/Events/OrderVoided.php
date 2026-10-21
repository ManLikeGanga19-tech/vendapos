<?php

declare(strict_types=1);

namespace App\Domain\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A previously completed sale was voided on the terminal and the change committed. */
final class OrderVoided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly string $orderId,
    ) {}
}
