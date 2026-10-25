<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalogue\Actions\FindStockTrackedItems;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Moves a branch's stock for the lines of one order: out for a sale, back in
 * for a void. Only stock-tracked items move. Changes are additive, so a void
 * applied before its sale still nets to zero.
 */
final class ApplyOrderToStock
{
    public function __construct(private readonly FindStockTrackedItems $findStockTrackedItems) {}

    /** @param list<array{item_id: string, quantity: int}> $lines */
    public function handle(string $branchId, string $orderId, string $eventId, array $lines, string $reason): void
    {
        $sign = $reason === StockMovement::REASON_SALE ? -1 : 1;
        $tracked = array_flip($this->findStockTrackedItems->handle(array_column($lines, 'item_id')));

        $quantities = [];

        foreach ($lines as $line) {
            if (isset($tracked[$line['item_id']])) {
                $quantities[$line['item_id']] = ($quantities[$line['item_id']] ?? 0) + $sign * $line['quantity'];
            }
        }

        if ($quantities === []) {
            return;
        }

        $now = now();

        StockMovement::query()->insert(array_map(fn (string $itemId, int $quantity) => [
            'branch_id' => $branchId,
            'item_id' => $itemId,
            'quantity' => $quantity,
            'reason' => $reason,
            'order_id' => $orderId,
            'event_id' => $eventId,
            'created_at' => $now,
        ], array_keys($quantities), $quantities));

        StockLevel::query()->upsert(
            array_map(fn (string $itemId, int $quantity) => [
                'branch_id' => $branchId,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys($quantities), $quantities),
            ['branch_id', 'item_id'],
            [
                'quantity' => DB::raw('stock_levels.quantity + excluded.quantity'),
                'updated_at' => DB::raw('excluded.updated_at'),
            ],
        );
    }
}
