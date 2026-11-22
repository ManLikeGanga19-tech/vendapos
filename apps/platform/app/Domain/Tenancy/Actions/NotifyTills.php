<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Device;
use App\Infrastructure\Realtime\BroadcastTillSync;
use App\Infrastructure\Realtime\TillsShouldSync;
use InvalidArgumentException;

/**
 * Tells a business's tills to sync now, after the current transaction
 * commits. Modules call this when something tills hold changes; the 60-second
 * sync stays as the fallback for tills that were offline.
 */
final class NotifyTills
{
    /**
     * Every unrevoked till of the business, or only those at $branchIds.
     *
     * @param  list<string>|null  $branchIds
     */
    public function ofTenant(string $tenantId, string $what, ?array $branchIds = null): void
    {
        $deviceIds = Device::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $this->send(array_values($deviceIds), $what);
    }

    /** One till, revoked or not: a revoked till learns it at once from its open connection. */
    public function device(string $deviceId, string $what = TillsShouldSync::DEVICE): void
    {
        $this->send([$deviceId], $what);
    }

    /** @param  list<string>  $deviceIds */
    private function send(array $deviceIds, string $what): void
    {
        if (! in_array($what, TillsShouldSync::WHAT, true)) {
            throw new InvalidArgumentException("Unknown till sync signal [{$what}].");
        }

        if ($deviceIds !== []) {
            BroadcastTillSync::dispatch($deviceIds, $what);
        }
    }
}
