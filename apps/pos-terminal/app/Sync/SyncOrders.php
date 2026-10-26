<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Sends the till's unsynced sales, oldest first, in batches of up to 50
 * (CLAUDE.md §3.5). Accepted and duplicate mean the server has the sale;
 * a rejected sale keeps the server's reason for a person to look at and is
 * never resent on its own. A network failure leaves everything pending.
 */
final class SyncOrders
{
    public const int BATCH = 50;

    /** Enough for a busy offline afternoon without holding one round for long. */
    private const int MAX_BATCHES = 10;

    public function __construct(private readonly VendaApi $api) {}

    /** How many sales the server now has. */
    public function handle(): int
    {
        $synced = 0;

        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            $orders = Order::query()->awaitingSync()->with(['lines', 'tenders'])->limit(self::BATCH)->get();

            if ($orders->isEmpty()) {
                break;
            }

            $results = collect($this->api->syncOrders(array_values($orders->map(fn (Order $order) => $order->toSyncPayload())->all())))
                ->filter(fn (array $result) => $result['uuid'] !== null)
                ->keyBy('uuid');

            foreach ($orders as $order) {
                $result = $results->get($order->id);

                // No answer for this sale: leave it pending for the next round.
                if ($result === null) {
                    continue;
                }

                if (in_array($result['status'], ['accepted', 'duplicate', 'updated'], true)) {
                    $order->update(['sync_status' => Order::SYNCED, 'sync_reason' => null, 'sync_errors' => null, 'synced_at' => now()]);
                    $synced++;

                    continue;
                }

                $order->update(['sync_status' => Order::REJECTED, 'sync_reason' => $result['reason'] ?? 'rejected', 'sync_errors' => $result['errors']]);
                Log::warning('till.order_rejected', ['order_id' => $order->id, 'sequence' => $order->sequence_number, 'reason' => $result['reason']]);
            }

            if ($orders->count() < self::BATCH) {
                break;
            }
        }

        return $synced;
    }
}
