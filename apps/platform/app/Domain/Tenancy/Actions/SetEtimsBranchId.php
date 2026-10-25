<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Branch;

/** Records the KRA branch ID (bhfId) a branch was registered under, or clears it. */
final class SetEtimsBranchId
{
    public function handle(string $branchId, ?string $etimsBranchId): void
    {
        $etimsBranchId = $etimsBranchId === null ? null : trim($etimsBranchId);

        Branch::query()->findOrFail($branchId)->update([
            'etims_branch_id' => $etimsBranchId === '' ? null : $etimsBranchId,
        ]);
    }
}
