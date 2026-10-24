<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * A purged tenant keeps its central row (the tombstone) but has no schema.
 * Jobs queued before the purge and the every-minute sweeps over all tenants
 * must leave it alone instead of failing inside a schema that is gone.
 */
final class LiveTenants
{
    /** True when the tenant exists and still has its data (active or suspended). */
    public static function has(string $tenantId): bool
    {
        return Tenant::query()->whereKey($tenantId)->where('status', '!=', Tenant::PURGED)->exists();
    }

    /**
     * The IDs of every tenant that still has its data, for tenancy()->runForMultiple().
     *
     * @return Collection<int, string>
     */
    public static function all(): Collection
    {
        /** @var Collection<int, string> $ids */
        $ids = Tenant::query()->where('status', '!=', Tenant::PURGED)->pluck('id');

        return $ids;
    }
}
