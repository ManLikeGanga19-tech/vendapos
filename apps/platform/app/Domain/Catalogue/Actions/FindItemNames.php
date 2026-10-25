<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Item;

/** The public way other code reads items' current names. */
final class FindItemNames
{
    /**
     * @param  list<string>  $itemIds
     * @return array<string, string> keyed by item ID
     */
    public function handle(array $itemIds): array
    {
        return Item::query()
            ->whereKey(array_values(array_unique($itemIds)))
            ->pluck('name', 'id')
            ->all();
    }
}
