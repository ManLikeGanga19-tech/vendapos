<?php

declare(strict_types=1);

namespace App\Domain\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use VendaPOS\Core\Enums\PaymentStatus;

/** A payment reached its final status (succeeded, failed, cancelled or timed out) and it is committed. */
final class PaymentCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly string $tenantId,
        public readonly string $paymentId,
        public readonly PaymentStatus $status,
    ) {}
}
