<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\VendaApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The cashier went back without taking payment: let another till take it.
 * Best effort; an unreleased hold lapses on the server by itself.
 */
final class ReleaseTicketHoldJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $ticketId,
        public readonly string $saleId,
    ) {}

    public function handle(VendaApi $api): void
    {
        try {
            $api->releaseTicket($this->ticketId, $this->saleId);
        } catch (Throwable) {
            // The hold lapses by itself.
        }
    }
}
