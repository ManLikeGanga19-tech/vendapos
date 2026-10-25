<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Jobs;

use App\Domain\Tenancy\Actions\ExportTenantSchema;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * An export asked for from the Admin panel, on the `exports` queue (long
 * timeout, one worker). One per tenant at a time. Not retried: a failed
 * export is recorded on its row and the admin can start another.
 */
final class ExportTenantData implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly string $tenantId, public readonly ?int $requestedBy = null)
    {
        $this->onConnection('redis-exports')->onQueue('exports');
        $this->timeout = (int) config('vendapos.exports.timeout_seconds') + 60;
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(ExportTenantSchema $export): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        // A purged tenant has no schema left to export.
        if (! $tenant instanceof Tenant || $tenant->status === Tenant::PURGED) {
            return;
        }

        $export->handle($tenant, TenantExport::MANUAL, $this->requestedBy);
    }
}
