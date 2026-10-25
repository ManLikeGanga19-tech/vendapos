<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Branch;

/** The public way the Etims module reads a branch's KRA branch ID (bhfId). */
final class FindEtimsBranchId
{
    public function handle(string $branchId): ?string
    {
        $etimsBranchId = Branch::query()->whereKey($branchId)->value('etims_branch_id');

        return is_string($etimsBranchId) ? $etimsBranchId : null;
    }
}
