<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Jobs;

use App\Domain\Tenancy\Actions\PurgeTenant;
use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One tenant's purge, on the long-running exports connection (it starts
 * with a full export). Not retried in place: if the export or the purge
 * fails, nothing is deleted and tomorrow's tenants:purge run tries again.
 */
final class PurgeTenantData implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly string $tenantId)
    {
        $this->onConnection('redis-exports')->onQueue('exports');
        $this->timeout = (int) config('vendapos.exports.timeout_seconds') + 300;
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(PurgeTenant $purge): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        try {
            $purge->handle($tenant);
        } catch (TenantStatusConflict) {
            // Reactivated or already purged since it was queued: nothing to do.
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('security')->critical('tenancy.tenant_purge_failed', [
            'tenant_id' => $this->tenantId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
