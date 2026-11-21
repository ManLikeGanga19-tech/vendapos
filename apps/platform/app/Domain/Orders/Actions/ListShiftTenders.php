<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderTender;
use VendaPOS\Core\Data\TenderData;

/**
 * Every tender of every sale made in one drawer shift, for cash-up. Sales
 * voided later are included: their cash came into the drawer, and what went
 * back out is the shift's own void-refund movement.
 */
final class ListShiftTenders
{
    /** @return list<TenderData> */
    public function handle(string $shiftId): array
    {
        return array_values(OrderTender::query()
            ->whereIn('order_id', Order::query()->select('id')->where('shift_id', $shiftId))
            ->orderBy('order_id')
            ->orderBy('position')
            ->get()
            ->map(fn (OrderTender $tender) => new TenderData(
                method: $tender->method,
                amount_cents: $tender->amount_cents,
                tendered_cents: $tender->tendered_cents,
                change_cents: $tender->change_cents,
                payment_uuid: $tender->payment_id,
                mpesa_reference: $tender->mpesa_reference,
            ))
            ->all());
    }
}
