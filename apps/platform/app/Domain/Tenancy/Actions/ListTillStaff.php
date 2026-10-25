<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who can sign in on a branch's tills: active staff with a PIN who work
 * there, and every owner. The till checks PINs and permissions offline, so
 * it gets the PIN hash, never the PIN. Always the whole list (a branch has
 * tens of people), so someone moved or switched off simply drops out.
 * Run inside the tenant's context.
 */
final class ListTillStaff
{
    /**
     * @return list<array{id: string, name: string, role: string, permissions: list<string>, pin_hash: string, pin_length: int}>
     */
    public function handle(string $branchId): array
    {
        return array_values(Staff::query()
            ->with(['roles.permissions:id,name'])
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->where(fn (Builder $query) => $query
                ->whereHas('roles', fn (Builder $query) => $query->where('name', 'owner'))
                ->orWhereHas('branches', fn (Builder $query) => $query->where('branches.id', $branchId)))
            ->orderBy('name')
            ->get()
            ->filter(fn (Staff $staff) => $staff->roleName() !== null)
            ->map(function (Staff $staff) {
                $role = (string) $staff->roleName();

                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'role' => $role,
                    'permissions' => array_values($staff->getAllPermissions()->pluck('name')->sort()->values()->map(fn (mixed $name): string => (string) $name)->all()),
                    'pin_hash' => (string) $staff->pin_hash,
                    'pin_length' => Staff::pinLengthFor($role),
                ];
            })
            ->all());
    }
}
