<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Support\StaffRules;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff a person may see in the Owner panel: everyone for an owner; for
 * a manager, the people who share a branch with them. Each row says whether
 * the person asking may change it. Run inside the tenant's context.
 */
final class ListStaff
{
    public function __construct(private readonly StaffRules $rules) {}

    /**
     * @return list<array{id: string, name: string, role: string|null, branches: list<string>, branch_ids: list<string>, is_active: bool, has_pin: bool, pin_set_at: string|null, panel_login: bool, manageable: bool, is_you: bool}>
     */
    public function handle(Staff $actor, bool $active = true): array
    {
        $isOwner = $actor->roleName() === 'owner';
        $branchIds = $actor->visibleBranchIds();

        return array_values(Staff::query()
            ->with(['roles:id,name', 'branches:id,name'])
            ->where('is_active', $active)
            ->when(! $isOwner, fn (Builder $query) => $query->whereHas('branches', fn (Builder $query) => $query->whereIn('branches.id', $branchIds)))
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->name,
                'role' => $staff->roleName(),
                'branches' => array_values($staff->branches->sortBy('name')->pluck('name')->map(fn (mixed $name): string => (string) $name)->all()),
                'branch_ids' => array_values($staff->branches->pluck('id')->map(fn (mixed $id): string => (string) $id)->all()),
                'is_active' => $staff->is_active,
                'has_pin' => $staff->pin_hash !== null,
                'pin_set_at' => $staff->pin_set_at?->toIso8601String(),
                'panel_login' => $staff->username !== null,
                'manageable' => $this->rules->canManage($actor, $staff),
                'is_you' => $staff->is($actor),
            ])
            ->all());
    }
}
