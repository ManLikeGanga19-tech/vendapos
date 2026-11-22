<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;

/**
 * The public way other modules check staff IDs against the current tenant.
 * Switched-off staff still count: a till may sync a sale made offline before
 * the person was switched off.
 */
final class FindUnknownStaff
{
    /**
     * @param  list<string>  $staffIds
     * @return list<string> IDs of no one at this business
     */
    public function handle(array $staffIds): array
    {
        $unique = array_values(array_unique($staffIds));
        $known = Staff::query()->whereKey($unique)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

        return array_values(array_diff($unique, $known));
    }
}
