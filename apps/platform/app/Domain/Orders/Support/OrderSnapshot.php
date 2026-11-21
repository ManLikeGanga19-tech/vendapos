<?php

declare(strict_types=1);

namespace App\Domain\Orders\Support;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderLine;
use App\Domain\Orders\Models\OrderTender;
use App\Domain\Orders\Models\OrderVoid;

/**
 * The outbox payload for every order event: a full snapshot, so each
 * listener can act on its own event without reading the Orders tables.
 */
final class OrderSnapshot
{
    /** @return array<string, mixed> */
    public static function of(Order $order): array
    {
        $void = $order->void;

        return [
            'order_id' => $order->id,
            'staff_id' => $order->staff_id,
            'ticket_id' => $order->ticket_id,
            'shift_id' => $order->shift_id,
            'sequence_number' => $order->sequence_number,
            'receipt_number' => $order->receipt_number,
            'status' => $order->status->value,
            'placed_at' => $order->placed_at->toIso8601String(),
            'payment_type' => $order->payment_type?->value,
            'subtotal_cents' => $order->subtotal_cents,
            'vat_cents' => $order->vat_cents,
            'total_cents' => $order->total_cents,
            'lines' => $order->lines->map(fn (OrderLine $line) => [
                'item_id' => $line->item_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'vat_category' => $line->vat_category->value,
                'line_total_cents' => $line->line_total_cents,
                'vat_cents' => $line->vat_cents,
            ])->values()->all(),
            'tenders' => $order->tenders->map(fn (OrderTender $tender) => [
                'method' => $tender->method->value,
                'amount_cents' => $tender->amount_cents,
                'tendered_cents' => $tender->tendered_cents,
                'change_cents' => $tender->change_cents,
                'payment_id' => $tender->payment_id,
                'mpesa_reference' => $tender->mpesa_reference,
            ])->values()->all(),
            'void' => $void instanceof OrderVoid ? [
                'device_id' => $void->device_id,
                'voided_at' => $void->voided_at->toIso8601String(),
                'voided_by' => $void->voided_by,
                'approved_by' => $void->approved_by,
                'reason' => $void->reason->value,
                'note' => $void->note,
                'approver_allowed' => $void->approver_allowed,
                'same_business_day' => $void->same_business_day,
            ] : null,
        ];
    }
}
