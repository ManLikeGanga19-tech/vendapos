<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Jobs;

use App\Domain\Analytics\Models\ItemSalesDaily;
use App\Domain\Analytics\Models\SalesHourly;
use App\Infrastructure\Outbox\DomainEvent;
use App\Infrastructure\Queue\TenantEventJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * order.recorded / order.voided → the branch's hourly sales and per-item
 * daily sales (architecture §7.3). The hour and day are Africa/Nairobi, from
 * when the sale was placed, not when it synced. Every change is additive, so
 * a void processed before its sale still nets out.
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
        $status = $payload['status'] ?? null;
        $voidEvent = $event->event_type === 'order.voided';

        // Sign applied to the order count, amounts and items:
        //   recorded completed +1 · order.voided −1 · recorded already voided or cancelled 0.
        $sign = match (true) {
            $voidEvent => -1,
            $status === 'voided', $status === 'cancelled' => 0,
            default => 1,
        };
        $voids = $voidEvent || $status === 'voided' ? 1 : 0;
        $cancels = ! $voidEvent && $status === 'cancelled' ? 1 : 0;

        $placedAt = Carbon::parse($payload['placed_at'])->setTimezone((string) config('vendapos.business_timezone'));
        $date = $placedAt->toDateString();
        $now = now();

        SalesHourly::query()->upsert(
            [[
                'branch_id' => $event->branch_id,
                'business_date' => $date,
                'hour' => $placedAt->hour,
                'order_count' => $sign,
                'void_count' => $voids,
                'cancelled_count' => $cancels,
                'subtotal_cents' => $sign * (int) $payload['subtotal_cents'],
                'vat_cents' => $sign * (int) $payload['vat_cents'],
                'total_cents' => $sign * (int) $payload['total_cents'],
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['branch_id', 'business_date', 'hour'],
            [
                'order_count' => DB::raw('sales_hourly.order_count + excluded.order_count'),
                'void_count' => DB::raw('sales_hourly.void_count + excluded.void_count'),
                'cancelled_count' => DB::raw('sales_hourly.cancelled_count + excluded.cancelled_count'),
                'subtotal_cents' => DB::raw('sales_hourly.subtotal_cents + excluded.subtotal_cents'),
                'vat_cents' => DB::raw('sales_hourly.vat_cents + excluded.vat_cents'),
                'total_cents' => DB::raw('sales_hourly.total_cents + excluded.total_cents'),
                'updated_at' => DB::raw('excluded.updated_at'),
            ],
        );

        if ($sign === 0) {
            return;
        }

        $items = [];

        /** @var list<array{item_id: string, quantity: int, line_total_cents: int, vat_cents: int}> $lines */
        $lines = $payload['lines'];

        foreach ($lines as $line) {
            $row = $items[$line['item_id']] ?? ['quantity' => 0, 'total_cents' => 0, 'vat_cents' => 0];
            $items[$line['item_id']] = [
                'quantity' => $row['quantity'] + $sign * (int) $line['quantity'],
                'total_cents' => $row['total_cents'] + $sign * (int) $line['line_total_cents'],
                'vat_cents' => $row['vat_cents'] + $sign * (int) $line['vat_cents'],
            ];
        }

        ItemSalesDaily::query()->upsert(
            array_map(fn (string $itemId, array $row) => [
                'branch_id' => $event->branch_id,
                'business_date' => $date,
                'item_id' => $itemId,
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys($items), $items),
            ['branch_id', 'business_date', 'item_id'],
            [
                'quantity' => DB::raw('item_sales_daily.quantity + excluded.quantity'),
                'total_cents' => DB::raw('item_sales_daily.total_cents + excluded.total_cents'),
                'vat_cents' => DB::raw('item_sales_daily.vat_cents + excluded.vat_cents'),
                'updated_at' => DB::raw('excluded.updated_at'),
            ],
        );
    }
}
