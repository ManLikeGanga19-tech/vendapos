<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends TillsShouldSync from the queue, so a save in a panel never waits on
 * Reverb. While one is waiting, the same signal to the same tills is not
 * queued again: saving twenty menu items still makes each till sync once or
 * twice, not twenty times. The lock lifts as sending starts, so a change
 * saved meanwhile is signalled again.
 */
final class BroadcastTillSync implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 10;

    /** @param  list<string>  $deviceIds */
    public function __construct(public readonly array $deviceIds, public readonly string $what)
    {
        $this->onQueue('broadcasts');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        $ids = $this->deviceIds;
        sort($ids);

        return $this->what.':'.hash('sha256', implode(',', $ids));
    }

    public function handle(): void
    {
        broadcast(new TillsShouldSync($this->deviceIds, $this->what));
    }
}
