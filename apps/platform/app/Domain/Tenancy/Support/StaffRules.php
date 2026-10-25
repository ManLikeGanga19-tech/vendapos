<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Support;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;

/**
 * Who may look after whom. Owners look after everyone. Managers look after
 * cashiers and waiters who work only in the manager's own branches, so a
 * manager never reaches a person, or a branch, outside their own.
 * Run inside the tenant's context.
 */
final class StaffRules
{
    /** @return list<string> */
    public function rolesFor(Staff $actor): array
    {
        return match ($actor->roleName()) {
            'owner' => Staff::ROLES,
            'manager' => Staff::TILL_ROLES,
            default => [],
        };
    }

    /** @return list<string> */
    public function branchIdsFor(Staff $actor): array
    {
        return match ($actor->roleName()) {
            'owner' => array_values(Branch::query()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all()),
            'manager' => $actor->visibleBranchIds(),
            default => [],
        };
    }

    public function canManage(Staff $actor, Staff $target): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        return match ($actor->roleName()) {
            'owner' => true,
            'manager' => in_array($target->roleName(), Staff::TILL_ROLES, true)
                && $this->worksOnlyIn($target, $actor->visibleBranchIds()),
            default => false,
        };
    }

    /** @param list<string> $branchIds */
    private function worksOnlyIn(Staff $target, array $branchIds): bool
    {
        // The loaded relation when listing many people; read fresh otherwise.
        $theirs = $target->branches->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

        return $theirs !== [] && array_diff($theirs, $branchIds) === [];
    }
}
