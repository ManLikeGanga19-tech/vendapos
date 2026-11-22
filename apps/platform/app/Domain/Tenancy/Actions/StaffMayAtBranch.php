<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;

/**
 * The public way other modules ask whether someone may do something at a
 * branch: active, holding the permission, and an owner or working there.
 * The same people a till would accept a PIN from for it (ListTillStaff).
 * Run inside the tenant's context.
 */
final class StaffMayAtBranch
{
    public function handle(string $staffId, string $permission, string $branchId): bool
    {
        $staff = Staff::query()->whereKey($staffId)->where('is_active', true)->first();

        if ($staff === null || ! $staff->checkPermissionTo($permission)) {
            return false;
        }

        return $staff->hasRole('owner') || $staff->branches()->where('branches.id', $branchId)->exists();
    }
}
