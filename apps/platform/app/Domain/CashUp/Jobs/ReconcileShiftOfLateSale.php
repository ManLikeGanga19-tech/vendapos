<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Jobs;

use App\Domain\CashUp\Actions\ReconcileShift;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;

/**
 * order.recorded → if the sale belongs to a shift that has already closed
 * (it synced after the close), the shift's figures are worked out again.
 * Reads only the event's snapshot for the shift, never the Orders tables.
 */
final class ReconcileShiftOfLateSale extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'default';
    }

    protected function process(DomainEvent $event): void
    {
        $shiftId = $event->payload['shift_id'] ?? null;

        if (is_string($shiftId)) {
            // Does nothing while the shift is still open: its close reconciles it.
            app(ReconcileShift::class)->handle($shiftId);
        }
    }
}
