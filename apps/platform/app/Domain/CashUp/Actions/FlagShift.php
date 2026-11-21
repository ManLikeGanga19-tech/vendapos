<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Actions;

use App\Domain\CashUp\Models\CashCheck;
use App\Domain\CashUp\Models\CashMovement;
use App\Domain\CashUp\Models\Shift;
use App\Domain\Tenancy\Actions\StaffMayAtBranch;
use Illuminate\Support\Carbon;

/**
 * Opens cash checks for a closed shift the server has just counted, and
 * settles the ones a late sale put right. Never refuses or reopens the
 * shift: the drawer was counted and the cash handed over by then.
 */
final class FlagShift
{
    /** Who may approve a close off by more than allowed, a pay-out or a drop. */
    public const string PERMISSION = 'shifts.close';

    public function __construct(private readonly StaffMayAtBranch $staffMayAtBranch) {}

    public function handle(Shift $shift): void
    {
        $key = "shift:{$shift->id}";
        $base = [
            'branch_id' => $shift->branch_id,
            'shift_id' => $shift->id,
            'staff_id' => $shift->closed_by,
            'approved_by' => $shift->approved_by,
            'business_date' => self::businessDate($shift->closed_at ?? $shift->opened_at),
            'details' => [
                'number' => $shift->number,
                'device_id' => $shift->device_id,
                'counted_cents' => $shift->counted_cents,
                'expected_cents' => $shift->expected_cents,
                'till_expected_cents' => $shift->till_expected_cents,
                'note' => $shift->note,
            ],
        ];

        $variance = (int) $shift->variance_cents;

        if (abs($variance) > self::allowedCents()) {
            CashCheck::open(CashCheck::VARIANCE_OVER_LIMIT, $key, [...$base, 'amount_cents' => $variance]);
        } else {
            CashCheck::settle(CashCheck::VARIANCE_OVER_LIMIT, $key);
        }

        if ($shift->till_expected_cents !== $shift->expected_cents) {
            CashCheck::open(CashCheck::FIGURES_DIFFER, $key, [...$base, 'amount_cents' => (int) $shift->till_expected_cents - (int) $shift->expected_cents]);
        } else {
            CashCheck::settle(CashCheck::FIGURES_DIFFER, $key);
        }

        if ($shift->approved_by !== null && ! $this->mayApprove($shift->approved_by, $shift->branch_id)) {
            CashCheck::open(CashCheck::APPROVER_NOT_ALLOWED, $key, [...$base, 'amount_cents' => $variance]);
        }

        $shift->movements()->whereNotNull('approved_by')->get()->each(function (CashMovement $movement) use ($shift) {
            if (! $this->mayApprove((string) $movement->approved_by, $shift->branch_id)) {
                CashCheck::open(CashCheck::APPROVER_NOT_ALLOWED, "movement:{$movement->id}", [
                    'branch_id' => $shift->branch_id,
                    'shift_id' => $shift->id,
                    'staff_id' => $movement->staff_id,
                    'approved_by' => $movement->approved_by,
                    'amount_cents' => $movement->amount_cents,
                    'business_date' => self::businessDate($movement->moved_at),
                    'details' => ['number' => $shift->number, 'kind' => $movement->kind->value, 'reason' => $movement->reason],
                ]);
            }
        });
    }

    /** The business's allowance, read from the tenant the job runs in. */
    public static function allowedCents(): int
    {
        $allowed = tenant('cash_variance_allowed_cents');

        return is_numeric($allowed) ? (int) $allowed : 10_000;
    }

    private function mayApprove(string $staffId, string $branchId): bool
    {
        return $this->staffMayAtBranch->handle($staffId, self::PERMISSION, $branchId);
    }

    private static function businessDate(Carbon $moment): string
    {
        return $moment->copy()->setTimezone((string) config('vendapos.business_timezone'))->toDateString();
    }
}
