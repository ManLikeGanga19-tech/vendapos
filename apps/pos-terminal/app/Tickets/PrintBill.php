<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Jobs\PrintBillJob;
use App\Jobs\PushTicketsJob;
use App\Models\Ticket;
use App\Models\TicketLine;

/**
 * The guest asks for the bill: print it (not a tax invoice) and mark the
 * ticket billed on every till. Ordering more afterwards reopens it.
 */
final class PrintBill
{
    /** @throws TicketRefused */
    public function handle(Ticket $ticket): void
    {
        if ($ticket->status === Ticket::CLOSED) {
            throw new TicketRefused('paid');
        }

        if (! $ticket->lines()->where('sync_status', '!=', TicketLine::REFUSED)->exists()) {
            throw new TicketRefused('empty');
        }

        $ticket->update([
            'status' => Ticket::BILL_REQUESTED,
            'bill_requested_at' => now()->utc(),
            'sync_status' => Ticket::PENDING,
        ]);

        PrintBillJob::dispatch($ticket->id);
        PushTicketsJob::dispatch();
    }
}
