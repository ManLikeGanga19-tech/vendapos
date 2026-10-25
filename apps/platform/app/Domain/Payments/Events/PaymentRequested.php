<?php

declare(strict_types=1);

namespace App\Domain\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A terminal asked for a mobile-money push and the pending payment is committed. */
final class PaymentRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly string $paymentId,
    ) {}
}
