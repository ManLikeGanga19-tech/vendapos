<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Jobs;

use App\Domain\Analytics\Models\DailySales;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * order.recorded / order.voided → the branch's daily sales. The day is the
 * Africa/Nairobi business day the sale was placed on, not the day it synced.
 */
final class UpdateSalesSummaries extends TenantEventJob
{
    protected static function queueName(): string
    {
        return 'analytics';
    }

    protected function process(DomainEvent $event): void
    {
        if ($event->branch_id === null) {
            throw new LogicException("Order event {$event->event_id} has no branch.");
        }

        $payload = $event->payload;

        // Never a sale. Counted for loss prevention once the hourly rollups land (slice G2).
        if (($payload['status'] ?? null) === 'cancelled') {
            return;
        }

        $voided = $event->event_type === 'order.voided';
        // An order that first syncs already voided ends where a sale plus its void would: one void, nothing sold.
        $voidedBeforeSync = ! $voided && ($payload['status'] ?? null) === 'voided';
        $sign = match (true) {
            $voided => -1,
            $voidedBeforeSync => 0,
            default => 1,
        };
        $now = now();

        DailySales::query()->upsert(
            [[
                'branch_id' => $event->branch_id,
                'business_date' => Carbon::parse($payload['placed_at'])
                    ->setTimezone((string) config('vendapos.business_timezone'))
                    ->toDateString(),
                'order_count' => $sign,
                'void_count' => $voided || $voidedBeforeSync ? 1 : 0,
                'subtotal_cents' => $sign * (int) $payload['subtotal_cents'],
                'vat_cents' => $sign * (int) $payload['vat_cents'],
                'total_cents' => $sign * (int) $payload['total_cents'],
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['branch_id', 'business_date'],
            [
                'order_count' => DB::raw('daily_sales.order_count + excluded.order_count'),
                'void_count' => DB::raw('daily_sales.void_count + excluded.void_count'),
                'subtotal_cents' => DB::raw('daily_sales.subtotal_cents + excluded.subtotal_cents'),
                'vat_cents' => DB::raw('daily_sales.vat_cents + excluded.vat_cents'),
                'total_cents' => DB::raw('daily_sales.total_cents + excluded.total_cents'),
                'updated_at' => DB::raw('excluded.updated_at'),
            ],
        );
    }
}
