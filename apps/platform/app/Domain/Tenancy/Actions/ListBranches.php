<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Branch;

/** The public way other code (e.g. the Admin panel) lists the current tenant's branches. */
final class ListBranches
{
    /** @return array<string, array{id: string, name: string, business_type: string, etims_branch_id: string|null}> keyed by branch ID */
    public function handle(): array
    {
        return Branch::query()->orderBy('name')->get()
            ->mapWithKeys(fn (Branch $branch) => [$branch->id => [
                'id' => $branch->id,
                'name' => $branch->name,
                'business_type' => $branch->business_type->value,
                'etims_branch_id' => $branch->etims_branch_id,
            ]])
            ->all();
    }
}
