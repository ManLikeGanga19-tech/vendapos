<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;

/** The current tenant's staff names by ID, removed staff included, for other modules' lists. */
final class FindStaffNames
{
    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function handle(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Staff::query()->whereKey(array_values(array_unique($ids)))->pluck('name', 'id')->map(fn (mixed $name): string => (string) $name)->all();
    }
}
