<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Actions;

use App\Domain\CashUp\Events\ShiftClosed;
use App\Domain\CashUp\Models\CashMovement;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Orders\Data\SyncResult;
use App\Domain\Tenancy\Actions\FindUnknownStaff;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\Data\CashCountData;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Data\ShiftData;
use VendaPOS\Core\Enums\KesDenomination;

/**
 * Durably records one synced drawer shift. A till sends a shift when it
 * opens, again with any new cash movements, and once more when it closes;
 * each send carries only the movements not yet accepted. What was accepted
 * never changes: the opening, every movement and, once closed, the close.
 * Resending is harmless. Closing records a shift.closed outbox event, and
 * the server then works out the expected cash itself after commit.
 */
final class RecordShift
{
    public function __construct(private readonly FindUnknownStaff $findUnknownStaff) {}

    public function handle(ShiftData $data, DeviceContext $context): SyncResult
    {
        if ($this->findUnknownStaff->handle($this->staffIds($data)) !== []) {
            return SyncResult::rejected($data->uuid, 'unknown_staff', ['staff' => 'Someone on this shift is not at this business.']);
        }

        try {
            return DB::transaction(fn () => $this->write($data, $context));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request committed this shift, or the till reused a shift number.
            return Shift::query()->whereKey($data->uuid)->exists()
                ? SyncResult::duplicate($data->uuid)
                : SyncResult::rejected($data->uuid, 'number_conflict', ['number' => 'This till already has a shift with this number.']);
        }
    }

    private function write(ShiftData $data, DeviceContext $context): SyncResult
    {
        $shift = Shift::query()->whereKey($data->uuid)->lockForUpdate()->first();

        if ($shift !== null && $shift->device_id !== $context->deviceId) {
            return SyncResult::rejected($data->uuid, 'uuid_conflict');
        }

        if ($shift !== null && ! $this->sameOpening($shift, $data)) {
            return SyncResult::rejected($data->uuid, 'shift_conflict', ['opening' => 'The shift was opened differently.']);
        }

        $movements = $this->newMovements($data, $shift);

        if (is_string($movements)) {
            return SyncResult::rejected($data->uuid, 'movement_conflict', ['movements' => "Movement {$movements} belongs to another shift."]);
        }

        if ($shift?->isClosed()) {
            if ($movements !== []) {
                return SyncResult::rejected($data->uuid, 'shift_closed', ['movements' => 'Cash cannot leave a closed shift.']);
            }

            // A stale "open" from a retried batch is a duplicate; a different close is a till bug.
            return ! $data->isClosed() || $this->sameClose($shift, $data)
                ? SyncResult::duplicate($data->uuid)
                : SyncResult::rejected($data->uuid, 'shift_conflict', ['closed_at' => 'The shift was closed differently.']);
        }

        $isNew = $shift === null;
        $shift ??= Shift::create([
            'id' => $data->uuid,
            'branch_id' => $context->branchId,
            'device_id' => $context->deviceId,
            'number' => $data->number,
            'opened_at' => Carbon::parse($data->opened_at)->utc(),
            'opened_by' => $data->opened_by,
            'opening_counts' => self::counts($data->opening_float),
            'float_cents' => $data->float()->cents,
        ]);

        foreach ($movements as $movement) {
            CashMovement::create([
                'id' => $movement->uuid,
                'shift_id' => $shift->id,
                'kind' => $movement->kind,
                'amount_cents' => $movement->amount_cents,
                'moved_at' => Carbon::parse($movement->moved_at)->utc(),
                'staff_id' => $movement->staff_id,
                'approved_by' => $movement->approved_by,
                'reason' => $movement->reason,
                'order_id' => $movement->order_uuid,
            ]);
        }

        if ($data->isClosed()) {
            $this->close($shift, $data, $context);
        }

        return match (true) {
            $isNew => SyncResult::accepted($shift->id),
            $movements !== [] || $data->isClosed() => SyncResult::updated($shift->id),
            default => SyncResult::duplicate($shift->id),
        };
    }

    private function close(Shift $shift, ShiftData $data, DeviceContext $context): void
    {
        $shift->update([
            'closed_at' => Carbon::parse((string) $data->closed_at)->utc(),
            'closed_by' => $data->closed_by,
            'closing_counts' => $data->closing_count === null ? null : self::counts($data->closing_count),
            'counted_cents' => $data->closing_count?->total()->cents,
            'till_expected_cents' => $data->expected_cents,
            'approved_by' => $data->approved_by,
            'note' => $data->note,
        ]);

        $event = DomainEvent::record(
            type: 'shift.closed',
            tenantId: $context->tenantId,
            payload: [
                'shift_id' => $shift->id,
                'number' => $shift->number,
                'closed_by' => $shift->closed_by,
                'counted_cents' => $shift->counted_cents,
                'till_expected_cents' => $shift->till_expected_cents,
                'approved_by' => $shift->approved_by,
            ],
            aggregateId: $shift->id,
            branchId: $context->branchId,
            deviceId: $context->deviceId,
        );

        ShiftClosed::dispatch($event->event_id, $context->tenantId, $shift->id);
    }

    /**
     * The movements this send adds, or the ID of one that already belongs to another shift.
     *
     * @return list<CashMovementData>|string
     */
    private function newMovements(ShiftData $data, ?Shift $shift): array|string
    {
        $sent = $data->movementList();
        $known = CashMovement::query()->whereKey(array_map(fn (CashMovementData $movement) => $movement->uuid, $sent))->pluck('shift_id', 'id')->all();
        $new = [];

        foreach ($sent as $movement) {
            if (! array_key_exists($movement->uuid, $known)) {
                $new[$movement->uuid] = $movement;
            } elseif ($known[$movement->uuid] !== $shift?->id) {
                return $movement->uuid;
            }
        }

        return array_values($new);
    }

    private function sameOpening(Shift $shift, ShiftData $data): bool
    {
        return $shift->number === $data->number
            && $shift->opened_by === $data->opened_by
            && $shift->opened_at->equalTo(Carbon::parse($data->opened_at))
            && self::sameCounts($shift->opening_counts, self::counts($data->opening_float));
    }

    private function sameClose(Shift $shift, ShiftData $data): bool
    {
        return $shift->closed_at?->equalTo(Carbon::parse((string) $data->closed_at)) === true
            && $shift->closed_by === $data->closed_by
            && self::sameCounts($shift->closing_counts ?? [], $data->closing_count === null ? [] : self::counts($data->closing_count))
            && $shift->till_expected_cents === $data->expected_cents
            && $shift->approved_by === $data->approved_by
            && $shift->note === $data->note;
    }

    /**
     * Counts kept the same way however the till listed them: largest note first, no zeros.
     *
     * @return array<int, int>
     */
    private static function counts(CashCountData $count): array
    {
        $counts = [];

        foreach (KesDenomination::cases() as $denomination) {
            $number = $count->countOf($denomination);

            if ($number > 0) {
                $counts[$denomination->value] = $number;
            }
        }

        return $counts;
    }

    /**
     * jsonb keeps its own key order and PHP turns "1000" into 1000, so counts
     * are compared denomination by denomination, never as stored.
     *
     * @param  array<int|string, int>  $stored
     * @param  array<int|string, int>  $sent
     */
    private static function sameCounts(array $stored, array $sent): bool
    {
        foreach (KesDenomination::cases() as $denomination) {
            if ((int) ($stored[$denomination->value] ?? 0) !== (int) ($sent[$denomination->value] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function staffIds(ShiftData $data): array
    {
        $ids = [$data->opened_by, $data->closed_by, $data->approved_by];

        foreach ($data->movementList() as $movement) {
            $ids[] = $movement->staff_id;
            $ids[] = $movement->approved_by;
        }

        return array_values(array_filter($ids, is_string(...)));
    }
}
