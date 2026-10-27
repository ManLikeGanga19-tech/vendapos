<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Models\Device;
use App\Domain\Verticals\Models\Ticket;
use App\Domain\Verticals\Models\TicketLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VendaPOS\Core\Data\OrderLineData;
use VendaPOS\Core\Tax\OrderTotals;

/**
 * GET /api/v1/tickets — the till's own branch's tickets not yet paid, in
 * full, oldest first. A ticket missing from the list has been paid.
 */
final class ShowTickets
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user('sanctum');

        $tickets = Ticket::query()
            ->where('branch_id', $device->branch_id)
            ->where('status', '!=', Ticket::CLOSED)
            ->with(['lines', 'table'])
            ->orderBy('opened_at')
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'table_id' => $ticket->dining_table_id,
                'table_name' => $ticket->table?->name,
                'label' => $ticket->label,
                'staff_id' => $ticket->staff_id,
                'opened_at' => $ticket->opened_at->toIso8601String(),
                'bill_requested_at' => $ticket->bill_requested_at?->toIso8601String(),
                'total_cents' => OrderTotals::fromLines($ticket->lines->map(fn (TicketLine $line) => new OrderLineData(
                    item_id: $line->item_id,
                    name: $line->name,
                    quantity: $line->quantity,
                    unit_price_cents: $line->unit_price_cents,
                    vat_category: $line->vat_category,
                    line_total_cents: $line->line_total_cents,
                ))->all())->total->cents,
                'lines' => $ticket->lines->map(fn (TicketLine $line) => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price_cents' => $line->unit_price_cents,
                    'vat_category' => $line->vat_category->value,
                    'line_total_cents' => $line->line_total_cents,
                    'staff_id' => $line->staff_id,
                    'sent_at' => $line->sent_at->toIso8601String(),
                ])->values()->all(),
            ])
            ->values()->all();

        return response()->json(['tickets' => $tickets]);
    }
}
