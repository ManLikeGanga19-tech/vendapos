<?php

declare(strict_types=1);

namespace App\Domain\Etims\Jobs;

use App\Domain\Etims\Actions\TransmitEtimsDocument;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one submission right after its order event is handled. Runs once:
 * a failed attempt is recorded and RetryFailedEtims takes it from there.
 */
final class SendEtimsDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $submissionId,
    ) {
        $this->onQueue('etims');
    }

    public function handle(TransmitEtimsDocument $transmit): void
    {
        // Purged since this was queued: nothing left to act on.
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $transmit->handle($this->submissionId);
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }
}
