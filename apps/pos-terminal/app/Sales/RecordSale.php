<?php

declare(strict_types=1);

namespace App\Sales;

use App\Jobs\PushOrdersJob;
use App\Models\Item;
use App\Models\Order;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Till\Till;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Payments\Tenders;
use VendaPOS\Core\Tax\OrderTotals;

/**
 * Writes a paid sale to the till's own database first (CLAUDE.md §3.5), with
 * totals and tender checks from packages/core — the same code the server
 * checks it with, so a sale accepted here is never refused there for its
 * arithmetic. The push to the server happens after, and works offline later.
 */
final class RecordSale
{
    public function __construct(private readonly Till $till) {}

    /**
     * @param  list<array{item_id: string, quantity: int}>  $cart
     * @param  list<TenderData>  $tenders
     * @param  string|null  $orderId  the sale's UUID when an M-Pesa payment was already asked for with it
     * @param  Ticket|null  $ticket  the ticket this sale pays: its sent lines, at the prices they were sent at, replace $cart
     *
     * @throws SaleRefused
     */
    public function handle(StaffMember $staff, array $cart, array $tenders, ?string $orderId = null, ?Ticket $ticket = null): Order
    {
        if (! $staff->can(StaffMember::TAKE_ORDERS) || ! $staff->can(StaffMember::TAKE_PAYMENTS)) {
            throw new SaleRefused('not_allowed');
        }

        if ($ticket !== null && $ticket->status === Ticket::CLOSED) {
            throw new SaleRefused('ticket_paid');
        }

        $lines = $ticket !== null
            ? array_map(fn (TicketLine $line) => $line->toOrderLine(), $ticket->load('lines')->billedLines())
            : ($cart === [] ? [] : $this->lines($cart));

        if ($lines === []) {
            throw new SaleRefused('empty');
        }

        $totals = OrderTotals::fromLines($lines);
        $problems = Tenders::problems($tenders, OrderStatus::Completed, $totals->total->cents);

        if ($problems !== []) {
            throw new SaleRefused('tenders', $problems);
        }

        $order = DB::transaction(function () use ($staff, $lines, $totals, $tenders, $orderId, $ticket): Order {
            // One writer (this till), so the next number is simply the highest plus one.
            $sequence = (int) Order::query()->max('sequence_number') + 1;

            $tillCode = $this->till->identity()?->till_code;

            $order = Order::query()->create([
                'id' => $orderId ?? (string) Str::uuid7(),
                'sequence_number' => $sequence,
                'receipt_number' => $tillCode !== null ? ReceiptNumber::format($tillCode, $sequence) : null,
                'staff_id' => $staff->id,
                'status' => OrderStatus::Completed->value,
                'placed_at' => now()->utc(),
                'subtotal_cents' => $totals->subtotal->cents,
                'vat_cents' => $totals->vat->cents,
                'total_cents' => $totals->total->cents,
                'sync_status' => Order::PENDING,
                'ticket_id' => $ticket?->id,
                'table_name' => $ticket?->table_name,
                'ticket_label' => $ticket?->label,
            ]);

            $order->lines()->createMany(array_map(fn (OrderLineData $line, int $index) => [
                'position' => $index + 1,
                'item_id' => $line->item_id,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unit_price_cents,
                'vat_category' => $line->vat_category->value,
                'line_total_cents' => $line->line_total_cents,
            ], $lines, array_keys($lines)));

            $order->tenders()->createMany(array_map(fn (TenderData $tender, int $index) => [
                'position' => $index + 1,
                'method' => $tender->method->value,
                'amount_cents' => $tender->amount_cents,
                'tendered_cents' => $tender->tendered_cents,
                'change_cents' => $tender->change_cents,
                'payment_uuid' => $tender->payment_uuid,
                'mpesa_reference' => $tender->mpesa_reference,
            ], $tenders, array_keys($tenders)));

            // Paid: off this till's queue now; the server closes it when the sale reaches it.
            $ticket?->update(['status' => Ticket::CLOSED, 'order_id' => $order->id]);

            return $order;
        });

        // Up to the server within seconds when online; the minute round catches it otherwise.
        PushOrdersJob::dispatch();

        return $order;
    }

    /**
     * Lines priced from the till's menu, never from the screen.
     *
     * @param  list<array{item_id: string, quantity: int}>  $cart
     * @return list<OrderLineData>
     */
    private function lines(array $cart): array
    {
        $items = Item::query()->where('is_active', true)->whereKey(array_column($cart, 'item_id'))->get()->keyBy('id');
        $lines = [];

        foreach ($cart as $entry) {
            $item = $items->get($entry['item_id']);

            if (! $item instanceof Item || $entry['quantity'] < 1) {
                throw new SaleRefused('unknown_item');
            }

            $lines[] = OrderLineData::from([
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $entry['quantity'],
                'unit_price_cents' => $item->price_cents,
                'vat_category' => $item->vat_category,
                'line_total_cents' => $item->price_cents * $entry['quantity'],
            ]);
        }

        return $lines;
    }
}
