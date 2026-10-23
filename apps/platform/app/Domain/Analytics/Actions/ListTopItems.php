<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\ItemSalesDaily;

/**
 * The current tenant's best-selling items by revenue over a range of
 * Africa/Nairobi business days, read from the daily item rollup. Items
 * that net to nothing (sold, then voided) are left out.
 */
final class ListTopItems
{
    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @return list<array{item_id: string, quantity: int, total_cents: int}>
     */
    public function handle(string $fromDate, string $toDate, ?array $branchIds = null, int $limit = 5): array
    {
        return array_values(ItemSalesDaily::query()
            ->whereBetween('business_date', [$fromDate, $toDate])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->groupBy('item_id')
            ->havingRaw('sum(quantity) <> 0 or sum(total_cents) <> 0')
            ->orderByRaw('sum(total_cents) desc, item_id')
            ->limit($limit)
            ->selectRaw('item_id, sum(quantity) as quantity, sum(total_cents) as total_cents')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'item_id' => (string) $row->item_id,
                'quantity' => (int) $row->quantity,
                'total_cents' => (int) $row->total_cents,
            ])
            ->all());
    }
}
