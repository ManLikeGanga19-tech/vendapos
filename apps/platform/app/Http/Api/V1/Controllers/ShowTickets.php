<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Domain\Tenancy\Models\Device;
use App\Domain\Verticals\Models\Ticket;
use App\Domain\Verticals\Models\TicketLine;
use App\Domain\Verticals\Models\TicketLineVoid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/tickets — the till's own branch's tickets not yet paid, in
 * full, oldest first. A ticket missing from the list has been paid. Lines
 * come as sent; what a manager took off comes in voids, and the total is
 * what is left to pay.
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
            ->with(['lines', 'voids', 'table'])
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
                'total_cents' => $ticket->totalCents(),
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
                'voids' => $ticket->voids->map(fn (TicketLineVoid $void) => [
                    'id' => $void->id,
                    'line_id' => $void->ticket_line_id,
                    'quantity' => $void->quantity,
                    'voided_at' => $void->voided_at->toIso8601String(),
                    'voided_by' => $void->voided_by,
                    'approved_by' => $void->approved_by,
                    'reason' => $void->reason->value,
                    'note' => $void->note,
                ])->values()->all(),
            ])
            ->values()->all();

        return response()->json(['tickets' => $tickets]);
    }
}
