<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Item;

/** The public way other modules learn which items hold stock (products and bundles, not services). */
final class FindStockTrackedItems
{
    /**
     * @param  list<string>  $itemIds
     * @return list<string> the IDs whose item type tracks stock
     */
    public function handle(array $itemIds): array
    {
        return array_values(Item::query()
            ->whereKey(array_values(array_unique($itemIds)))
            ->get(['id', 'type'])
            ->filter(fn (Item $item) => $item->type->tracksStock())
            ->map(fn (Item $item) => $item->id)
            ->all());
    }
}
