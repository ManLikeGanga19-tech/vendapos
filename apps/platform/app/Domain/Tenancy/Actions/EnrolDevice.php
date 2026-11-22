<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EnrolDevice
{
    public function __construct(private readonly IssueDeviceToken $issueToken) {}

    public function handle(Tenant $tenant, string $branchId, string $name): DeviceEnrolment
    {
        // Existing tills keep working while suspended; no new ones join.
        if (! $tenant->isActive()) {
            throw new InvalidArgumentException("Tenant [{$tenant->getTenantKey()}] is {$tenant->status}; no new devices can be enrolled.");
        }

        // The branch lives in the tenant's schema: look it up there, never across tenants.
        $branchExists = $tenant->run(fn () => Branch::query()->whereKey($branchId)->exists());

        if (! $branchExists) {
            throw new InvalidArgumentException("Branch [{$branchId}] does not belong to tenant [{$tenant->getTenantKey()}].");
        }

        $device = DB::connection((new Device)->getConnectionName())->transaction(function () use ($tenant, $branchId, $name) {
            // Till numbers are per business: hold the tenant row while the next one is taken.
            Tenant::query()->whereKey($tenant->getTenantKey())->lockForUpdate()->first();

            return Device::create([
                'tenant_id' => $tenant->getTenantKey(),
                'branch_id' => $branchId,
                'name' => $name,
            ]);
        });

        return new DeviceEnrolment($device, $this->issueToken->handle($device)->plainTextToken);
    }
}
