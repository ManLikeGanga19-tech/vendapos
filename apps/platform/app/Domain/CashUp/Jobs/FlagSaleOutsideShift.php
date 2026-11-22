<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Jobs;

use App\Domain\CashUp\Models\CashCheck;
use App\Domain\CashUp\Models\Shift;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use Illuminate\Support\Carbon;

/**
 * order.recorded → a sale with no shift, from a till that had already
 * opened shifts by then, was made outside cash-up: the owner should know.
 * A till older than cash-up sends no shifts at all and is not flagged.
 * Reads only the event's snapshot for the sale, never the Orders tables.
 */
final class FlagSaleOutsideShift extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'default';
    }

    protected function process(DomainEvent $event): void
    {
        $payload = $event->payload;

        if (($payload['shift_id'] ?? null) !== null || $event->device_id === null || ($payload['tenders'] ?? []) === []) {
            return;
        }

        $placedAt = Carbon::parse((string) $payload['placed_at']);

        if (! Shift::query()->where('device_id', $event->device_id)->where('opened_at', '<=', $placedAt)->exists()) {
            return;
        }

        CashCheck::open(CashCheck::SALE_OUTSIDE_SHIFT, "order:{$event->aggregate_id}", [
            'branch_id' => $event->branch_id,
            'order_id' => $event->aggregate_id,
            'staff_id' => $payload['staff_id'] ?? null,
            'amount_cents' => (int) ($payload['total_cents'] ?? 0),
            'business_date' => $placedAt->copy()->setTimezone((string) config('vendapos.business_timezone'))->toDateString(),
            'details' => ['receipt_number' => $payload['receipt_number'] ?? null, 'device_id' => $event->device_id],
        ]);
    }
}
