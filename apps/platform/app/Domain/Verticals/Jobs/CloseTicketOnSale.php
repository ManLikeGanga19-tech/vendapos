<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Jobs;

use App\Domain\Verticals\Actions\CloseTicket;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use Illuminate\Support\Carbon;

/**
 * order.recorded → close the ticket the sale paid. Reads only the event's
 * snapshot, never the Orders tables. A cancelled sale closes nothing.
 */
final class CloseTicketOnSale extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'default';
    }

    protected function process(DomainEvent $event): void
    {
        $ticketId = $event->payload['ticket_id'] ?? null;

        if (! is_string($ticketId) || ($event->payload['status'] ?? null) !== 'completed' || $event->branch_id === null) {
            return;
        }

        app(CloseTicket::class)->paidBy(
            $ticketId,
            (string) $event->aggregate_id,
            (string) $event->branch_id,
            Carbon::parse((string) $event->payload['placed_at']),
        );
    }
}
