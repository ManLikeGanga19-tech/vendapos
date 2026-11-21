<?php

declare(strict_types=1);

namespace App\CashUp;

use App\Jobs\PushOrdersJob;
use App\Models\Shift;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use VendaPOS\Core\Data\CashCountData;
use VendaPOS\Core\Data\ShiftData;

/**
 * Closes the open shift with the cashier's blind count. Within the allowance
 * it closes there and then; beyond it a manager's PIN (checked before this
 * is called) and a note are needed. The till's expected figure goes to the
 * server with the count, and the server works its own out to compare.
 */
final class CloseShift
{
    public function __construct(private readonly Drawer $drawer) {}

    /** What the count says before anything is saved: expected, difference and whether a manager is needed. */
    public function check(Shift $shift, CashCountData $count): CountResult
    {
        $cash = $this->drawer->cash($shift);
        $counted = $count->total();

        return new CountResult(
            expected: $cash->expected(),
            counted: $counted,
            variance: $cash->variance($counted),
            needsApproval: $cash->needsApproval($counted, $this->drawer->allowance()),
        );
    }

    /** @throws CashUpRefused */
    public function handle(StaffMember $closer, CashCountData $count, ?StaffMember $approver = null, ?string $note = null): Shift
    {
        if (! $closer->can(StaffMember::TAKE_PAYMENTS)) {
            throw new CashUpRefused('not_allowed');
        }

        $shift = DB::transaction(function () use ($closer, $count, $approver, $note): Shift {
            $shift = $this->drawer->current() ?? throw new CashUpRefused('no_shift');
            $result = $this->check($shift, $count);
            $note = $note === null || trim($note) === '' ? null : trim($note);
            // Only an approved difference carries a manager and a note.
            $approval = ['approved_by' => null, 'note' => null];

            if ($result->needsApproval) {
                if ($approver === null) {
                    throw new CashUpRefused('approval_needed');
                }

                if (! $approver->can(StaffMember::CLOSE_SHIFTS)) {
                    throw new CashUpRefused('approver_not_allowed');
                }

                if ($note === null || mb_strlen($note) > ShiftData::NOTE_MAX) {
                    throw new CashUpRefused('note_needed');
                }

                $approval = ['approved_by' => $approver->id, 'note' => $note];
            }

            $shift->update([
                'closed_at' => now()->utc(),
                'closed_by' => $closer->id,
                'closing_counts' => $count->counts,
                'expected_cents' => $result->expected->cents,
                ...$approval,
                'close_synced' => false,
            ]);

            return $shift;
        });

        PushOrdersJob::dispatch();

        return $shift;
    }
}
