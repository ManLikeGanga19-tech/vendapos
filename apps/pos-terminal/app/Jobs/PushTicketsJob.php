<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Security\KeyringUnavailable;
use App\Sync\SyncTickets;
use App\Till\Till;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a ticket right after an order is sent or a bill printed, so the
 * kitchen side and the cashier see it within seconds. Failing quietly is
 * fine: the ticket stays pending and the minute round (SyncTillJob) sends it.
 */
final class PushTicketsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(SyncTickets $tickets, Till $till): void
    {
        if (! $till->isPaired()) {
            return;
        }

        try {
            $tickets->push();
        } catch (ApiUnavailable|ApiRejected|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            // Offline or token trouble: SyncTill deals with it on its next round.
        }
    }
}
