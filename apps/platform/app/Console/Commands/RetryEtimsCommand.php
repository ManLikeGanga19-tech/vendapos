<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Etims\Jobs\RetryFailedEtims;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Queue\LiveTenants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use VendaPOS\Core\Enums\EtimsStatus;

#[Signature('etims:retry')]
#[Description('Queue a retry for every tenant with eTIMS documents due to be sent')]
final class RetryEtimsCommand extends Command
{
    public function handle(): int
    {
        $queued = 0;

        tenancy()->runForMultiple(LiveTenants::all(), function (Tenant $tenant) use (&$queued) {
            $due = EtimsSubmission::query()
                ->where('status', EtimsStatus::Pending)
                ->where('next_attempt_at', '<=', now())
                ->exists();

            if ($due) {
                RetryFailedEtims::dispatch((string) $tenant->getTenantKey());
                $queued++;
            }
        });

        $this->info("Queued eTIMS retries for {$queued} tenants.");

        return self::SUCCESS;
    }
}
