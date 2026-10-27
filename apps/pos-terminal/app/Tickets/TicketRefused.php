<?php

declare(strict_types=1);

namespace App\Tickets;

use RuntimeException;

/** An order could not be sent to a ticket; $reason picks the message (terminal.tickets.refused.*). */
final class TicketRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Ticket refused: {$reason}");
    }
}
