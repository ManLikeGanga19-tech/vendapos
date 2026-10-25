<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Item;

/** The public way other modules check item IDs against the current tenant's catalogue. */
final class FindUnknownItems
{
    /**
     * @param  list<string>  $itemIds
     * @return list<string> IDs that are not in the catalogue
     */
    public function handle(array $itemIds): array
    {
        $unique = array_values(array_unique($itemIds));
        $known = Item::query()->whereKey($unique)->pluck('id')->all();

        return array_values(array_diff($unique, $known));
    }
}
