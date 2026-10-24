<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantStatusChange;
use App\Domain\Tenancy\Notifications\TenantSuspended;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suspends an active tenant: its panels and new mobile-money requests are
 * blocked at once, its tills keep syncing, and unless reactivated its data
 * is exported and deleted after the configured number of days. The owner is
 * told by SMS and email once the change commits.
 */
final class SuspendTenant
{
    public function handle(Tenant $tenant, string $reason, User $by): Tenant
    {
        return DB::connection($tenant->getConnectionName())->transaction(function () use ($tenant, $reason, $by) {
            $locked = Tenant::query()->whereKey($tenant->getTenantKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new TenantStatusConflict("Tenant [{$locked->getTenantKey()}] is {$locked->status}, not active.");
            }

            $locked->forceFill([
                'status' => Tenant::SUSPENDED,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'purge_after' => now()->addDays((int) config('vendapos.suspension.purge_after_days')),
                'suspended_by' => $by->getKey(),
            ])->save();

            TenantStatusChange::query()->create([
                'tenant_id' => $locked->getTenantKey(),
                'action' => TenantStatusChange::SUSPENDED,
                'reason' => $reason,
                'actor_id' => $by->getKey(),
                'details' => ['purge_after' => $locked->purge_after?->toIso8601String()],
            ]);

            $locked->notify(new TenantSuspended($locked->purge_after?->toImmutable() ?? now()->toImmutable()));

            return $locked;
        });
    }
}
