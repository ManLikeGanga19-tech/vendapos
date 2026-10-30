<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\Models\Ticket;
use App\Security\KeyringUnavailable;
use App\Sync\SyncTickets;

/**
 * A ticket is paid once, on one till (Daniel, 2026-10-30). Before the cashier
 * takes payment for a ticket, the server must hold it for this sale. Offline,
 * only a ticket no other till has ever seen can be paid: any other may already
 * have been paid elsewhere.
 */
final class HoldForPayment
{
    public const string HELD = 'held';

    public const string PAID = 'ticket_paid';

    public const string BEING_PAID = 'ticket_being_paid';

    public const string UNKNOWN = 'unknown_ticket';

    public const string NEEDS_INTERNET = 'needs_internet';

    public function __construct(
        private readonly VendaApi $api,
        private readonly SyncTickets $tickets,
    ) {}

    /** @return self::HELD|self::PAID|self::BEING_PAID|self::NEEDS_INTERNET */
    public function handle(Ticket $ticket, string $saleId): string
    {
        try {
            $outcome = $this->api->holdTicket($ticket->id, $saleId);

            // The server does not have it yet: send it now, then ask again.
            if ($outcome === self::UNKNOWN) {
                $this->tickets->push();
                $outcome = $this->api->holdTicket($ticket->id, $saleId);
            }
        } catch (ApiUnavailable|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            return $ticket->refresh()->on_server ? self::NEEDS_INTERNET : self::HELD;
        }

        if ($outcome === self::PAID) {
            // Paid on another till: it leaves this till's queue now, not at the next sync.
            $ticket->update(['status' => Ticket::CLOSED]);
        }

        return match ($outcome) {
            // Still not on the server, e.g. it refused the ticket: only this till knows it.
            self::UNKNOWN => $ticket->refresh()->on_server ? self::NEEDS_INTERNET : self::HELD,
            default => $outcome,
        };
    }
}
