<?php

declare(strict_types=1);

namespace App\CashUp;

use App\Jobs\PushOrdersJob;
use App\Models\CashMovement;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Data\ShiftData;
use VendaPOS\Core\Enums\CashMovementKind;

/**
 * A pay-out (cash spent on the business, with what it was for) or a drop to
 * the safe, from the open shift's drawer. A manager's PIN approves each one,
 * checked before this is called, even when the manager is the one signed in.
 * The drawer never gives out more than it should hold.
 */
final class TakeCashOut
{
    public function __construct(private readonly Drawer $drawer) {}

    /** @throws CashUpRefused */
    public function handle(StaffMember $staff, StaffMember $approver, CashMovementKind $kind, int $amountCents, ?string $reason): CashMovement
    {
        if ($kind === CashMovementKind::VoidRefund) {
            throw new CashUpRefused('not_allowed');
        }

        if (! $staff->can(StaffMember::TAKE_PAYMENTS)) {
            throw new CashUpRefused('not_allowed');
        }

        if (! $approver->can(StaffMember::CLOSE_SHIFTS)) {
            throw new CashUpRefused('approver_not_allowed');
        }

        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

        if (($kind->needsReason() && $reason === null) || mb_strlen((string) $reason) > CashMovementData::REASON_MAX) {
            throw new CashUpRefused('reason_needed');
        }

        if ($amountCents < 100) {
            throw new CashUpRefused('amount');
        }

        $movement = DB::transaction(function () use ($staff, $approver, $kind, $amountCents, $reason): CashMovement {
            $shift = $this->drawer->current() ?? throw new CashUpRefused('no_shift');

            if ($shift->movements()->count() >= ShiftData::MAX_MOVEMENTS) {
                throw new CashUpRefused('too_many');
            }

            if ($amountCents > $this->drawer->cash($shift)->expected()->cents) {
                throw new CashUpRefused('more_than_drawer');
            }

            return CashMovement::query()->create([
                'id' => (string) Str::uuid7(),
                'shift_id' => $shift->id,
                'kind' => $kind,
                'amount_cents' => $amountCents,
                'moved_at' => now()->utc(),
                'staff_id' => $staff->id,
                'approved_by' => $approver->id,
                // Only a pay-out says what it was for.
                'reason' => $kind->needsReason() ? $reason : null,
                'synced' => false,
            ]);
        });

        PushOrdersJob::dispatch();

        return $movement;
    }
}
