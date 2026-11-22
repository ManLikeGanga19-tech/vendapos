<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Sync\SyncTill;
use App\Till\Till;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a sync round, then queues itself again. NativePHP's scheduler only
 * ticks once a minute, so it is a watchdog that restarts this chain if the
 * worker died between rounds; the unique lock keeps it to one chain.
 */
final class SyncTillJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function uniqueFor(): int
    {
        return (int) config('terminal.sync_interval_seconds') * 3;
    }

    public function handle(SyncTill $sync, Till $till): void
    {
        try {
            $sync->handle();
        } finally {
            if ($till->isPaired()) {
                self::dispatch()->delay(now()->addSeconds((int) config('terminal.sync_interval_seconds')));
            }
        }
    }
}
