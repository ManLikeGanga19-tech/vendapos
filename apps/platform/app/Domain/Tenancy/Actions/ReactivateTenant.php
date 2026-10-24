<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\TenantStatusConflict;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantStatusChange;
use App\Domain\Tenancy\Notifications\TenantReactivated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Lifts a suspension before the purge: everything works again and the purge date is cleared. */
final class ReactivateTenant
{
    public function handle(Tenant $tenant, User $by): Tenant
    {
        return DB::connection($tenant->getConnectionName())->transaction(function () use ($tenant, $by) {
            $locked = Tenant::query()->whereKey($tenant->getTenantKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isSuspended()) {
                throw new TenantStatusConflict("Tenant [{$locked->getTenantKey()}] is {$locked->status}, not suspended.");
            }

            $locked->forceFill([
                'status' => Tenant::ACTIVE,
                'suspended_at' => null,
                'suspension_reason' => null,
                'purge_after' => null,
                'suspended_by' => null,
            ])->save();

            TenantStatusChange::query()->create([
                'tenant_id' => $locked->getTenantKey(),
                'action' => TenantStatusChange::REACTIVATED,
                'actor_id' => $by->getKey(),
            ]);

            $locked->notify(new TenantReactivated);

            return $locked;
        });
    }
}
