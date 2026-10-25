<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\ItemSalesDaily;

/** How many different items ListTopItems would rank for the same range, branches and items. */
final class CountSoldItems
{
    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @param  list<string>|null  $itemIds  null for every item
     */
    public function handle(string $fromDate, string $toDate, ?array $branchIds = null, ?array $itemIds = null): int
    {
        $sold = ItemSalesDaily::query()
            ->whereBetween('business_date', [$fromDate, $toDate])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($itemIds !== null, fn ($query) => $query->whereIn('item_id', $itemIds))
            ->groupBy('item_id')
            ->havingRaw('sum(quantity) <> 0 or sum(total_cents) <> 0')
            ->select('item_id');

        return ItemSalesDaily::query()->fromSub($sold, 'sold')->count();
    }
}
