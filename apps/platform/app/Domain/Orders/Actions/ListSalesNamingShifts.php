<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Sales placed in a window that name a drawer shift, for cash-up to find
 * shifts that never arrived. Bounded by time so the sweep stays cheap.
 */
final class ListSalesNamingShifts
{
    /** @return list<array{order_id: string, shift_id: string, branch_id: string, device_id: string, staff_id: string|null, receipt_number: string|null, total_cents: int, placed_at: Carbon}> */
    public function handle(Carbon $from, Carbon $to): array
    {
        return array_values(Order::query()
            ->whereNotNull('shift_id')
            ->whereBetween('placed_at', [$from, $to])
            ->get(['id', 'shift_id', 'branch_id', 'device_id', 'staff_id', 'receipt_number', 'total_cents', 'placed_at'])
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'shift_id' => (string) $order->shift_id,
                'branch_id' => $order->branch_id,
                'device_id' => $order->device_id,
                'staff_id' => $order->staff_id,
                'receipt_number' => $order->receipt_number,
                'total_cents' => $order->total_cents,
                'placed_at' => $order->placed_at,
            ])
            ->all());
    }
}
