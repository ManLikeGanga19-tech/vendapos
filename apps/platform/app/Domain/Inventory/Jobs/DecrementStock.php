<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Jobs;

use App\Domain\Inventory\Actions\ApplyOrderToStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;

/** order.recorded → take the sold items out of the branch's stock. */
final class DecrementStock extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'inventory';
    }

    protected function process(DomainEvent $event): void
    {
        // Voided while offline, or cancelled before completion: nothing left the shelf, and no
        // order.voided will follow to put it back.
        if (in_array($event->payload['status'] ?? null, ['voided', 'cancelled'], true)) {
            return;
        }

        app(ApplyOrderToStock::class)->handle(...StockEvent::arguments($event, StockMovement::REASON_SALE));
    }
}
