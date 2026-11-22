<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Actions;

use App\Domain\Tenancy\Data\DeviceContext;
use App\Domain\Verticals\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * A ticket is paid once, on one till (Daniel, 2026-10-30). Before taking
 * payment for a ticket, a till asks the server to hold it for that sale; a
 * ticket already paid, or held for another sale, is refused. A hold lapses
 * after a while so a till that went away cannot keep a table from paying.
 */
final class HoldTicketForPayment
{
    public const string HELD = 'held';

    public const string PAID = 'ticket_paid';

    public const string BEING_PAID = 'ticket_being_paid';

    public const string UNKNOWN = 'unknown_ticket';

    /** @return self::HELD|self::PAID|self::BEING_PAID|self::UNKNOWN */
    public function hold(string $ticketId, string $saleId, DeviceContext $context): string
    {
        return DB::transaction(function () use ($ticketId, $saleId, $context): string {
            $ticket = Ticket::query()->whereKey($ticketId)->where('branch_id', $context->branchId)->lockForUpdate()->first();

            if ($ticket === null) {
                return self::UNKNOWN;
            }

            // Closed, or paid by a sale that reached the server before its ticket did.
            if ($ticket->status === Ticket::CLOSED || DB::table('ticket_closures')->where('ticket_id', $ticketId)->exists()) {
                return self::PAID;
            }

            if ($ticket->held_for_order_id !== null && $ticket->held_for_order_id !== $saleId && $ticket->held_until?->isFuture()) {
                return self::BEING_PAID;
            }

            $ticket->update([
                'held_for_order_id' => $saleId,
                'held_by_device_id' => $context->deviceId,
                'held_until' => now()->addMinutes((int) config('vendapos.tickets.payment_hold_minutes')),
            ]);

            return self::HELD;
        });
    }

    /** The cashier went back or took no payment: let another till take it. */
    public function release(string $ticketId, string $saleId, DeviceContext $context): void
    {
        Ticket::query()
            ->whereKey($ticketId)
            ->where('branch_id', $context->branchId)
            ->where('held_for_order_id', $saleId)
            ->update(['held_for_order_id' => null, 'held_by_device_id' => null, 'held_until' => null]);
    }
}
