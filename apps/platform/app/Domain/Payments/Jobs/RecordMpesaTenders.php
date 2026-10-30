<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\CloseBillQrs;
use App\Domain\Payments\Actions\MatchMpesaCodes;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;

/**
 * order.recorded → note the sale's M-Pesa tenders: typed codes to confirm,
 * pushes now claimed, and the bill's other QR codes closed. Reads only the
 * event's snapshot, never the Orders tables.
 */
final class RecordMpesaTenders extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'payments';
    }

    protected function process(DomainEvent $event): void
    {
        if (($event->payload['status'] ?? null) !== 'completed') {
            return;
        }

        /** @var list<array<string, mixed>> $tenders */
        $tenders = array_values(array_filter((array) ($event->payload['tenders'] ?? []), is_array(...)));

        app(MatchMpesaCodes::class)->recordSale((string) $event->aggregate_id, (string) $event->branch_id, $tenders);
        app(CloseBillQrs::class)->handle((string) $event->aggregate_id);
    }
}
