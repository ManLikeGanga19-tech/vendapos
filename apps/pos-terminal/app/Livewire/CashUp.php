<?php

declare(strict_types=1);

namespace App\Livewire;

use App\CashUp\CashUpRefused;
use App\CashUp\CloseShift;
use App\CashUp\Count;
use App\CashUp\Drawer;
use App\CashUp\OpenShift;
use App\CashUp\ShiftReport;
use App\CashUp\TakeCashOut;
use App\Jobs\PrintCashSlipJob;
use App\Jobs\PrintShiftReportJob;
use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Models\Shift;
use App\Models\StaffMember;
use App\Printing\PrinterSettings;
use App\Staff\CheckPin;
use App\Staff\PinOutcome;
use App\Staff\TillSession;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Data\ShiftData;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\KesDenomination;
use VendaPOS\Core\ValueObjects\Money;

/**
 * The drawer: open a shift by counting the float, take cash out (pay-out or
 * drop, a manager's PIN every time), see the X report (managers), and close
 * with a blind count. The cashier never sees the expected cash before
 * counting. Off by more than the allowance: one recount, then a manager's
 * PIN and a note close it.
 */
final class CashUp extends Component
{
    use FollowsTillLanguage;
    use NeedsSignedInStaff;

    public const string OPEN = 'open';

    public const string SHIFT = 'shift';

    public const string OUT = 'out';

    public const string X = 'x';

    public const string COUNT = 'count';

    public const string DIFFERENCE = 'difference';

    public const string NOTE = 'note';

    public const string APPROVER = 'approver';

    public const string PIN = 'pin';

    public const string CLOSED = 'closed';

    /** Steps where the person is counting money: the idle timer waits. */
    public const array COUNTING = [self::OPEN, self::COUNT];

    #[Locked]
    public string $step = self::OPEN;

    /** @var array<int|string, string> what is typed, by shillings */
    public array $counts = [];

    /** @var array<int, int> the closing count as it was when "Count done" was tapped */
    #[Locked]
    public array $counted = [];

    #[Locked]
    public bool $recounted = false;

    /** @var array{expected_cents: int, counted_cents: int, variance_cents: int}|null */
    #[Locked]
    public ?array $result = null;

    #[Locked]
    public ?string $outKind = null;

    public string $amount = '';

    public string $reason = '';

    public string $note = '';

    /** What the manager's PIN approves: "out" (pay-out or drop) or "close". */
    #[Locked]
    public ?string $purpose = null;

    #[Locked]
    public ?string $approverId = null;

    public string $pin = '';

    #[Locked]
    public ?string $closedShiftId = null;

    public ?string $message = null;

    public ?string $notice = null;

    public function mount(TillSession $session, Drawer $drawer): void
    {
        abort_unless($session->can(StaffMember::TAKE_PAYMENTS), 403);

        $this->step = $drawer->isOpen() ? self::SHIFT : self::OPEN;
        $this->resetCounts();
    }

    public function openShift(OpenShift $openShift, TillSession $session): void
    {
        $staff = $session->staff();

        if ($this->step !== self::OPEN || $staff === null) {
            return;
        }

        try {
            $openShift->handle($staff, Count::of($this->counts));
        } catch (CashUpRefused $e) {
            $this->refused($e);

            return;
        }

        $this->redirectRoute('home');
    }

    public function startOut(string $kind, Drawer $drawer): void
    {
        $kind = CashMovementKind::tryFrom($kind);

        if ($this->step !== self::SHIFT || $kind === null || $kind === CashMovementKind::VoidRefund || ! $drawer->isOpen()) {
            return;
        }

        $this->reset('amount', 'reason', 'message', 'notice');
        $this->outKind = $kind->value;
        $this->step = self::OUT;
    }

    public function confirmOut(Drawer $drawer): void
    {
        $kind = $this->outKind === null ? null : CashMovementKind::tryFrom($this->outKind);
        $shift = $drawer->current();

        if ($this->step !== self::OUT || $kind === null || $shift === null) {
            return;
        }

        $this->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reason' => [$kind->needsReason() ? 'required' : 'nullable', 'string', 'max:'.CashMovementData::REASON_MAX],
        ], attributes: ['amount' => __('terminal.cash_up.amount'), 'reason' => __('terminal.cash_up.reason')]);

        // Said before the manager walks over, and checked again when it is saved.
        if ((int) $this->amount * 100 > $drawer->cash($shift)->expected()->cents) {
            $this->message = __('terminal.cash_up.refused.more_than_drawer');

            return;
        }

        $this->askApprover('out');
    }

    public function showX(TillSession $session, Drawer $drawer): void
    {
        if ($this->step === self::SHIFT && $drawer->isOpen() && $session->can(StaffMember::CLOSE_SHIFTS)) {
            $this->reset('message', 'notice');
            $this->step = self::X;
        }
    }

    public function printReport(Drawer $drawer): void
    {
        $shiftId = $this->step === self::CLOSED ? $this->closedShiftId : ($this->step === self::X ? $drawer->current()?->id : null);

        if ($shiftId !== null) {
            PrintShiftReportJob::dispatch($shiftId);
            $this->notice = __('terminal.cash_up.printing');
        }
    }

    public function startClose(Drawer $drawer): void
    {
        if ($this->step !== self::SHIFT || ! $drawer->isOpen()) {
            return;
        }

        $this->reset('message', 'notice', 'recounted', 'result', 'counted', 'note');
        $this->resetCounts();
        $this->step = self::COUNT;
    }

    public function countDone(CloseShift $closeShift, Drawer $drawer, TillSession $session): void
    {
        $shift = $drawer->current();
        $staff = $session->staff();

        if ($this->step !== self::COUNT || $shift === null || $staff === null) {
            return;
        }

        try {
            $count = Count::of($this->counts);
        } catch (CashUpRefused $e) {
            $this->refused($e);

            return;
        }

        $this->counted = [];

        foreach ($count->counts as $shillings => $number) {
            $this->counted[(int) $shillings] = $number;
        }
        $check = $closeShift->check($shift, $count);

        if (! $check->needsApproval) {
            $this->close($closeShift, $staff);

            return;
        }

        // Shown only now the count is in: the cashier counted blind.
        $this->result = ['expected_cents' => $check->expected->cents, 'counted_cents' => $check->counted->cents, 'variance_cents' => $check->variance->cents];
        $this->step = self::DIFFERENCE;
    }

    public function recount(): void
    {
        if ($this->step !== self::DIFFERENCE || $this->recounted) {
            return;
        }

        $this->recounted = true;
        $this->reset('result', 'counted', 'message');
        $this->resetCounts();
        $this->step = self::COUNT;
    }

    public function askApproval(): void
    {
        if ($this->step === self::DIFFERENCE) {
            $this->reset('note', 'message');
            $this->step = self::NOTE;
        }
    }

    public function confirmNote(): void
    {
        if ($this->step !== self::NOTE) {
            return;
        }

        $this->validate(['note' => ['required', 'string', 'max:'.ShiftData::NOTE_MAX]], attributes: ['note' => __('terminal.cash_up.note')]);

        $this->askApprover('close');
    }

    public function chooseApprover(string $staffId): void
    {
        $approver = StaffMember::query()->find($staffId);

        if ($this->step !== self::APPROVER || $approver === null || ! $approver->can(StaffMember::CLOSE_SHIFTS)) {
            return;
        }

        $this->approverId = $staffId;
        $this->pin = '';
        $this->message = null;
        $this->step = self::PIN;
    }

    public function press(string $digit, CheckPin $checkPin, TakeCashOut $takeCashOut, CloseShift $closeShift, TillSession $session): void
    {
        $approver = $this->approverId === null ? null : StaffMember::query()->find($this->approverId);
        $staff = $session->staff();

        if ($this->step !== self::PIN || $approver === null || $staff === null || ! ctype_digit($digit) || strlen($digit) !== 1) {
            return;
        }

        $this->pin .= $digit;
        $this->message = null;

        // No OK button: the PIN is checked as soon as it is long enough.
        if (strlen($this->pin) < $approver->pin_length) {
            return;
        }

        $outcome = $checkPin->handle($approver, $this->pin);
        $this->pin = '';

        if (! $outcome->isAccepted()) {
            $this->message = $outcome->result === PinOutcome::LOCKED
                ? __('terminal.sign_in.locked', ['time' => $outcome->lockedUntil?->setTimezone(StatusBar::TIMEZONE)->format('H:i')])
                : trans_choice('terminal.sign_in.wrong', $outcome->triesLeft, ['count' => $outcome->triesLeft]);

            return;
        }

        if ($this->purpose === 'close') {
            $this->close($closeShift, $staff, $approver);

            return;
        }

        try {
            $movement = $takeCashOut->handle($staff, $approver, CashMovementKind::from((string) $this->outKind), (int) $this->amount * 100, $this->reason);
        } catch (CashUpRefused $e) {
            $this->refused($e);
            $this->step = self::SHIFT;

            return;
        }

        PrintCashSlipJob::dispatch($movement->id);
        $this->reset('outKind', 'amount', 'reason', 'purpose', 'approverId');
        $this->notice = __("terminal.cash_up.out_done.{$movement->kind->value}", ['amount' => Money::fromCents($movement->amount_cents)->format()]);
        $this->step = self::SHIFT;
    }

    public function backspace(): void
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    /** One step back; from the shift itself, back to selling. */
    public function back(): void
    {
        $this->message = null;
        $this->pin = '';

        match ($this->step) {
            self::PIN => $this->step = self::APPROVER,
            self::APPROVER => $this->step = $this->purpose === 'close' ? self::NOTE : self::OUT,
            self::NOTE => $this->step = self::DIFFERENCE,
            self::OUT, self::X, self::COUNT => $this->step = self::SHIFT,
            // A count over the allowance is settled before leaving: a recount or a manager.
            self::DIFFERENCE => null,
            default => $this->redirectRoute('home'),
        };
    }

    /** After a close: the next person counts their float in. */
    public function openNext(Drawer $drawer): void
    {
        if ($this->step === self::CLOSED && ! $drawer->isOpen()) {
            $this->reset('message', 'notice', 'closedShiftId', 'result', 'counted', 'recounted', 'note');
            $this->resetCounts();
            $this->step = self::OPEN;
        }
    }

    /** Keeps the session alive while money is being counted (the idle timer is held). */
    public function keepAlive(): void {}

    public function render(TillSession $session, Drawer $drawer, Till $till): View
    {
        $shift = $drawer->current();
        $reportShift = match ($this->step) {
            self::X => $shift,
            self::CLOSED => $this->closedShiftId === null ? null : Shift::query()->find($this->closedShiftId),
            default => null,
        };

        return view('livewire.cash-up', [
            'staff' => $session->staff(),
            'shift' => $shift,
            'shiftLabel' => $shift === null ? null : ShiftReport::label($till->identity()?->till_code, $shift->number),
            'openedBy' => $shift === null ? null : StaffMember::query()->find($shift->opened_by)?->name,
            'report' => $reportShift === null ? null : ShiftReport::for($reportShift, $drawer, $till),
            'denominations' => KesDenomination::cases(),
            'typedTotal' => $this->typedTotal(),
            'approvers' => StaffMember::query()->orderBy('name')->get()->filter(fn (StaffMember $m) => $m->can(StaffMember::CLOSE_SHIFTS))->values(),
            'approver' => $this->approverId === null ? null : StaffMember::query()->find($this->approverId),
            'printerReady' => PrinterSettings::current()->isSet(),
        ]);
    }

    private function close(CloseShift $closeShift, StaffMember $staff, ?StaffMember $approver = null): void
    {
        try {
            $shift = $closeShift->handle($staff, Count::of($this->counted), $approver, $approver === null ? null : $this->note);
        } catch (CashUpRefused $e) {
            $this->refused($e);

            return;
        }

        PrintShiftReportJob::dispatch($shift->id);
        $this->reset('purpose', 'approverId', 'pin', 'message');
        $this->closedShiftId = $shift->id;
        $this->step = self::CLOSED;
    }

    private function askApprover(string $purpose): void
    {
        $this->purpose = $purpose;
        $this->reset('approverId', 'pin', 'message');
        $this->step = self::APPROVER;
    }

    private function refused(CashUpRefused $e): void
    {
        $this->message = __("terminal.cash_up.refused.{$e->reason}");
    }

    private function resetCounts(): void
    {
        $this->counts = [];

        foreach (KesDenomination::cases() as $denomination) {
            $this->counts[$denomination->value] = '';
        }
    }

    /** What has been typed so far, for the running total; anything not a whole number counts as none. */
    private function typedTotal(): Money
    {
        $total = Money::zero();

        foreach (KesDenomination::cases() as $denomination) {
            $typed = (string) ($this->counts[$denomination->value] ?? '');

            if (ctype_digit($typed) && strlen($typed) <= 5) {
                $total = $total->add($denomination->value()->multiply((int) $typed));
            }
        }

        return $total;
    }
}
