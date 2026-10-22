<?php

declare(strict_types=1);

namespace App\Domain\Etims\Jobs;

use App\Domain\Etims\Actions\TransmitEtimsDocument;
use App\Domain\Etims\Models\EtimsSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use VendaPOS\Core\Enums\EtimsStatus;

/** Sends one tenant's documents whose retry is due, oldest first (architecture §6.1, etims-retry). */
final class RetryFailedEtims implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(public readonly string $tenantId)
    {
        $this->onQueue('etims-retry');
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(TransmitEtimsDocument $transmit): void
    {
        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            EtimsSubmission::query()
                ->where('status', EtimsStatus::Pending)
                ->where('next_attempt_at', '<=', now())
                ->orderBy('next_attempt_at')
                ->limit((int) config('vendapos.etims.retry_batch_size'))
                ->pluck('id')
                ->each(fn (string $id) => $transmit->handle($id));
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }
}
