<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Actions\MatchMpesaCodes;
use App\Domain\Payments\Models\MpesaConfirmation;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** A C2B confirmation was stored: settle the typed codes waiting on it. Safe to run twice. */
final class MatchMpesaConfirmation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $confirmationId,
    ) {
        $this->onQueue('payments');
    }

    public function handle(MatchMpesaCodes $match): void
    {
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $confirmation = MpesaConfirmation::query()->find($this->confirmationId);

            if ($confirmation !== null) {
                DB::transaction(fn () => $match->confirmationArrived($confirmation));
            }
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }
}
