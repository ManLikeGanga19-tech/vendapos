<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\ItemSalesDaily;
use InvalidArgumentException;

/**
 * The current tenant's best-selling items over a range of Africa/Nairobi
 * business days, by revenue or by quantity, read from the daily item rollup.
 * Items that net to nothing (sold, then voided) are left out.
 */
final class ListTopItems
{
    public const string BY_REVENUE = 'revenue';

    public const string BY_QUANTITY = 'quantity';

    /**
     * @param  list<string>|null  $branchIds  null for every branch
     * @param  list<string>|null  $itemIds  null for every item (e.g. to search by name first)
     * @return list<array{item_id: string, quantity: int, total_cents: int}>
     */
    public function handle(
        string $fromDate,
        string $toDate,
        ?array $branchIds = null,
        int $limit = 5,
        string $rankBy = self::BY_REVENUE,
        ?array $itemIds = null,
        int $offset = 0,
    ): array {
        $order = match ($rankBy) {
            self::BY_REVENUE => 'sum(total_cents) desc, sum(quantity) desc, item_id',
            self::BY_QUANTITY => 'sum(quantity) desc, sum(total_cents) desc, item_id',
            default => throw new InvalidArgumentException("Unknown ranking [{$rankBy}]."),
        };

        return array_values(ItemSalesDaily::query()
            ->whereBetween('business_date', [$fromDate, $toDate])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($itemIds !== null, fn ($query) => $query->whereIn('item_id', $itemIds))
            ->groupBy('item_id')
            ->havingRaw('sum(quantity) <> 0 or sum(total_cents) <> 0')
            ->orderByRaw($order)
            ->offset($offset)
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
