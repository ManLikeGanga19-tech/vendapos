<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenancy\Jobs\PurgeTenantData;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenants:purge')]
#[Description('Queue the export and deletion of every suspended tenant whose purge date has passed')]
final class PurgeTenantsCommand extends Command
{
    public function handle(): int
    {
        $due = Tenant::query()
            ->where('status', Tenant::SUSPENDED)
            ->where('purge_after', '<=', now())
            ->pluck('id');

        $due->each(fn (string $tenantId) => PurgeTenantData::dispatch($tenantId));

        $this->info("Queued {$due->count()} tenant purges.");

        return self::SUCCESS;
    }
}
