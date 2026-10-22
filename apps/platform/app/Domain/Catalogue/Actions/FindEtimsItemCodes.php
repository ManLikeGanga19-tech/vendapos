<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Models\Item;

/** The public way the Etims module reads items' eTIMS codes (classification, units). */
final class FindEtimsItemCodes
{
    /**
     * @param  list<string>  $itemIds
     * @return array<string, array<string, mixed>|null> keyed by item ID
     */
    public function handle(array $itemIds): array
    {
        return Item::query()
            ->whereKey(array_values(array_unique($itemIds)))
            ->get(['id', 'etims'])
            ->mapWithKeys(fn (Item $item) => [$item->id => $item->etims])
            ->all();
    }
}
