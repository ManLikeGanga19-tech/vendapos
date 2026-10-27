<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Actions;

use App\Domain\Tenancy\Actions\NotifyTills;
use App\Domain\Verticals\Models\Ticket;
use App\Infrastructure\Realtime\TillsShouldSync;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A sale paid a ticket: the ticket closes and leaves every till's queue. The
 * payment is kept even when the ticket has not reached the server yet, so it
 * closes when it does. Only a ticket of the sale's own branch is closed.
 */
final class CloseTicket
{
    public function __construct(private readonly NotifyTills $notifyTills) {}

    public function paidBy(string $ticketId, string $orderId, string $branchId, Carbon $paidAt): void
    {
        $recorded = DB::table('ticket_closures')->insertOrIgnore([
            'ticket_id' => $ticketId,
            'order_id' => $orderId,
            'branch_id' => $branchId,
            'closed_at' => $paidAt->utc(),
        ]);

        $first = DB::table('ticket_closures')->where('ticket_id', $ticketId)->value('order_id');

        if ($recorded === 0 && $first !== $orderId) {
            // Two sales for one ticket, e.g. two tills paid it while offline. Both sales stand.
            Log::warning('tickets.paid_twice', ['ticket_id' => $ticketId, 'order_id' => $orderId, 'first_order_id' => $first]);

            return;
        }

        $closed = Ticket::query()
            ->whereKey($ticketId)
            ->where('branch_id', $branchId)
            ->where('status', '!=', Ticket::CLOSED)
            ->update(['status' => Ticket::CLOSED, 'order_id' => $orderId, 'closed_at' => $paidAt->utc(), 'updated_at' => now()]);

        $tenantId = tenant('id');

        if ($closed > 0 && is_string($tenantId)) {
            $this->notifyTills->ofTenant($tenantId, TillsShouldSync::TICKETS, [$branchId]);
        }
    }
}
