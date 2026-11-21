<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Jobs;

use App\Domain\CashUp\Actions\ReconcileShift;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;

/** shift.closed → the server works out the drawer's expected cash itself. */
final class ReconcileClosedShift extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'default';
    }

    protected function process(DomainEvent $event): void
    {
        app(ReconcileShift::class)->handle((string) $event->aggregate_id);
    }
}
