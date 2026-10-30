<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\Order;
use App\Models\ReceiptReprint;
use Illuminate\Support\Facades\Log;

/**
 * Sends the receipts printed again on this till to the owner's audit, in
 * batches of up to 50, once the server has their sale. A rejected one keeps
 * the server's reason and is never resent on its own.
 */
final class SyncReprints
{
    public const int BATCH = 50;

    private const int MAX_BATCHES = 10;

    public function __construct(private readonly VendaApi $api) {}

    public function handle(): void
    {
        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            $reprints = ReceiptReprint::query()->readyToSync()->limit(self::BATCH)->get();

            if ($reprints->isEmpty()) {
                break;
            }

            $results = collect($this->api->syncReprints(array_values($reprints->map(fn (ReceiptReprint $reprint) => $reprint->toSyncPayload())->all())))
                ->filter(fn (array $result) => $result['uuid'] !== null)
                ->keyBy('uuid');

            foreach ($reprints as $reprint) {
                $result = $results->get($reprint->id);

                if ($result === null) {
                    continue;
                }

                if (in_array($result['status'], ['accepted', 'duplicate'], true)) {
                    $reprint->update(['sync_status' => Order::SYNCED, 'sync_reason' => null, 'synced_at' => now()]);

                    continue;
                }

                $reprint->update(['sync_status' => Order::REJECTED, 'sync_reason' => $result['reason'] ?? 'rejected']);
                Log::warning('till.reprint_rejected', ['reprint_id' => $reprint->id, 'order_id' => $reprint->order_id, 'reason' => $result['reason']]);
            }

            if ($reprints->count() < self::BATCH) {
                break;
            }
        }
    }
}
