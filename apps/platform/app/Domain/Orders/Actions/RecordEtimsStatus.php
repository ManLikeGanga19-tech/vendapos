<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\Order;
use VendaPOS\Core\Enums\EtimsStatus;

/** The public way the Etims module records where an order's sale document stands. */
final class RecordEtimsStatus
{
    /** @param array<string, mixed>|null $receipt what KRA returned once the sale was accepted */
    public function handle(string $orderId, EtimsStatus $status, ?array $receipt = null): void
    {
        Order::query()->whereKey($orderId)->update([
            'etims_status' => $status,
            ...($receipt === null ? [] : ['etims_receipt' => $receipt]),
        ]);
    }
}
