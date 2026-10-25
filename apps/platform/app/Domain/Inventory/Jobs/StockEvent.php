<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Jobs;

use App\Infrastructure\Outbox\DomainEvent;
use LogicException;

/** Reads an order event's outbox row into ApplyOrderToStock's arguments. */
final class StockEvent
{
    /**
     * @return array{branchId: string, orderId: string, eventId: string, lines: list<array{item_id: string, quantity: int}>, reason: string}
     */
    public static function arguments(DomainEvent $event, string $reason): array
    {
        if ($event->branch_id === null || $event->aggregate_id === null) {
            throw new LogicException("Order event {$event->event_id} has no branch or order.");
        }

        /** @var list<array{item_id: string, quantity: int}> $lines */
        $lines = $event->payload['lines'];

        return [
            'branchId' => $event->branch_id,
            'orderId' => $event->aggregate_id,
            'eventId' => $event->event_id,
            'lines' => $lines,
            'reason' => $reason,
        ];
    }
}
