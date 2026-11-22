<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Security\KeyringUnavailable;
use App\Sync\SyncOrders;
use App\Sync\SyncShifts;
use App\Till\Till;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends new drawer shifts and cash movements, then new sales, right after
 * they are made: shifts first, so a sale never names one the server lacks.
 * Failing quietly is fine: everything stays pending and the minute round
 * (SyncTillJob) sends it, and handles an expired token too.
 */
final class PushOrdersJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(SyncShifts $shifts, SyncOrders $orders, Till $till): void
    {
        if (! $till->isPaired()) {
            return;
        }

        try {
            $shifts->handle();
            $orders->handle();
        } catch (ApiUnavailable|ApiRejected|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            // Offline or token trouble: SyncTill deals with it on its next round.
        }
    }
}
