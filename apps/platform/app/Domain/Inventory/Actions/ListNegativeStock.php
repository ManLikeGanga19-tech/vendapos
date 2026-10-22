<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockLevel;

/** The current tenant's stock levels below zero, most negative first (decision 5: flagged, never refused). */
final class ListNegativeStock
{
    /** @return list<array{branch_id: string, item_id: string, quantity: int}> */
    public function handle(): array
    {
        return array_values(StockLevel::query()->negative()->orderBy('quantity')->get()
            ->map(fn (StockLevel $level) => [
                'branch_id' => $level->branch_id,
                'item_id' => $level->item_id,
                'quantity' => $level->quantity,
            ])
            ->all());
    }
}
