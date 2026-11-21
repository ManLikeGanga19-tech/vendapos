<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\CashMovement;
use App\Models\Shift;
use Illuminate\Support\Facades\Log;
use VendaPOS\Core\Data\ShiftData;

/**
 * Sends what the server does not have of this till's drawer shifts: new
 * shifts, cash movements not yet accepted, and closes. Runs before sales, so
 * a sale never names a shift the server has not heard of.
 *
 * A send stays under the server's request size limit: a busy shift's
 * movements go over several sends, and its close only rides with the last
 * of them, since the server takes no cash out of a closed shift.
 */
final class SyncShifts
{
    public const int BATCH = 50;

    /** Under the server's 64 KB request limit (vendapos.api_body_limit_kb), with room to spare. */
    public const int MAX_BYTES = 56_000;

    private const int MAX_ROUNDS = 10;

    public function __construct(private readonly VendaApi $api) {}

    /** How many shift sends the server took. */
    public function handle(): int
    {
        $taken = 0;

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $batch = $this->batch();

            if ($batch === []) {
                break;
            }

            $results = collect($this->api->syncShifts(array_column($batch, 'payload')))
                ->filter(fn (array $result) => $result['uuid'] !== null)
                ->keyBy('uuid');

            foreach ($batch as $sent) {
                $result = $results->get($sent['shift']->id);

                // No answer for this shift: it goes again next round.
                if ($result !== null && $this->settle($sent['shift'], $sent['movements'], $sent['closed'], $result)) {
                    $taken++;
                }
            }

            if ($results->isEmpty()) {
                break;
            }
        }

        return $taken;
    }

    /**
     * The shifts and movements of one send, oldest first, as many as fit.
     *
     * @return list<array{shift: Shift, movements: list<CashMovement>, closed: bool, payload: array<string, mixed>}>
     */
    private function batch(): array
    {
        $batch = [];
        $bytes = 20; // {"shifts":[]}

        foreach (Shift::query()->awaitingSync()->limit(self::BATCH)->get() as $shift) {
            $pending = $shift->movements()->where('synced', false)->limit(ShiftData::MAX_MOVEMENTS)->get()->all();
            $closeLeft = $shift->isClosed() && ! $shift->close_synced;
            $all = count($pending) === $shift->movements()->where('synced', false)->count();

            // Fewer movements until it fits; the close only with every movement before it.
            for ($take = count($pending); $take >= 0; $take = $take === 0 ? -1 : intdiv($take, 2)) {
                $movements = array_slice($pending, 0, $take);
                $closed = $closeLeft && $all && $take === count($pending);
                $payload = $shift->toSyncPayload($movements, $closed);
                $size = strlen((string) json_encode($payload)) + 1;

                if ($bytes + $size <= self::MAX_BYTES) {
                    $batch[] = ['shift' => $shift, 'movements' => $movements, 'closed' => $closed, 'payload' => $payload];
                    $bytes += $size;

                    continue 2;
                }
            }

            // Not even the bare shift fits beside the others: it leads the next send.
            break;
        }

        return $batch;
    }

    /**
     * @param  list<CashMovement>  $movements
     * @param  array{uuid: string|null, status: string, reason: string|null, errors: array<mixed>}  $result
     */
    private function settle(Shift $shift, array $movements, bool $closed, array $result): bool
    {
        if (in_array($result['status'], ['accepted', 'duplicate', 'updated'], true)) {
            CashMovement::query()->whereKey(array_map(fn (CashMovement $movement) => $movement->id, $movements))->update(['synced' => true]);
            $shift->update([
                'sync_status' => Shift::SYNCED,
                'close_synced' => $shift->close_synced || $closed,
                'sync_reason' => null,
                'sync_errors' => null,
                'synced_at' => now(),
            ]);

            return true;
        }

        // Kept for a person to look at; never resent on its own.
        $shift->update(['sync_status' => Shift::REJECTED, 'sync_reason' => $result['reason'] ?? 'rejected', 'sync_errors' => $result['errors']]);
        Log::warning('till.shift_rejected', ['shift_id' => $shift->id, 'number' => $shift->number, 'reason' => $result['reason']]);

        return false;
    }
}
