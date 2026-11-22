<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderLine;
use App\Domain\Orders\Models\OrderTender;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\OrderStatus;

/**
 * A branch's sales of the current business day (Africa/Nairobi), newest
 * first, from every till: what a till shows to find a sale to void.
 * Cancelled sales were never sales and are left out.
 */
final class ListSalesToday
{
    /** More than a busy branch rings up in a day; the till searches within it. */
    public const int LIMIT = 500;

    /** @return list<array<string, mixed>> */
    public function handle(string $branchId): array
    {
        $timezone = (string) config('vendapos.business_timezone');
        $start = Carbon::now($timezone)->startOfDay()->utc();

        return array_values(Order::query()
            ->where('branch_id', $branchId)
            ->where('placed_at', '>=', $start)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->with(['lines', 'tenders', 'void'])
            ->orderByDesc('placed_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'device_id' => $order->device_id,
                'receipt_number' => $order->receipt_number,
                'staff_id' => $order->staff_id,
                'ticket_id' => $order->ticket_id,
                'status' => $order->status->value,
                'placed_at' => $order->placed_at->toIso8601String(),
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
                ])->values()->all(),
                'tenders' => $order->tenders->map(fn (OrderTender $tender) => [
                    'method' => $tender->method->value,
                    'amount_cents' => $tender->amount_cents,
                    'mpesa_reference' => $tender->mpesa_reference,
                ])->values()->all(),
                'void' => $order->void === null ? null : [
                    'voided_at' => $order->void->voided_at->toIso8601String(),
                    'voided_by' => $order->void->voided_by,
                    'approved_by' => $order->void->approved_by,
                    'reason' => $order->void->reason->value,
                    'note' => $order->void->note,
                ],
            ])
            ->all());
    }
}
