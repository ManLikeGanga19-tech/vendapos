<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Models\StaffMember;
use App\Models\Ticket;
use App\Models\TicketLine;
use App\Staff\CheckPin;
use App\Staff\PinOutcome;
use App\Staff\TillSession;
use App\Tickets\TicketRefused;
use App\Tickets\VoidTicketLine;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\TerminalMode;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Takes some or all of a sent item off an unpaid ticket (T5c): how many,
 * why, then a manager or owner approves with their PIN, every time. A waiter
 * may start it. Back on the sale screen the ticket stays open.
 */
final class TakeLineOff extends Component
{
    use FollowsTillLanguage;
    use NeedsSignedInStaff;

    public const string QUANTITY = 'quantity';

    public const string REASON = 'reason';

    public const string APPROVER = 'approver';

    public const string PIN = 'pin';

    public string $ticketId;

    public string $lineId;

    public string $step = self::QUANTITY;

    public int $quantity = 1;

    public ?string $reason = null;

    public string $note = '';

    public ?string $approverId = null;

    public string $pin = '';

    public ?string $message = null;

    public function mount(TillSession $session, string $ticketId, string $lineId): void
    {
        abort_unless($session->can(StaffMember::TAKE_ORDERS), 403);
        // Tickets belong to table service only (T7).
        abort_unless(app(Till::class)->identity()?->terminal_mode === TerminalMode::TableService->value, 404);

        $this->ticketId = $ticketId;
        $this->lineId = $lineId;
        $line = $this->line();

        if ($line === null) {
            $this->backToTicket();

            return;
        }

        // One left: nothing to choose.
        $this->step = $line->remainingQuantity() > 1 ? self::QUANTITY : self::REASON;
    }

    public function less(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function more(): void
    {
        $this->quantity = min($this->line()?->remainingQuantity() ?? 1, $this->quantity + 1);
    }

    public function confirmQuantity(): void
    {
        $this->step = self::REASON;
    }

    public function pickReason(string $reason): void
    {
        $picked = VoidReason::tryFrom($reason);

        if ($picked === null || ! in_array($picked, VoidReason::forTicketLines(), true)) {
            return;
        }

        $this->reason = $reason;
        $this->message = null;

        // "Other" waits for its note; the rest go straight on.
        if (! $picked->needsNote()) {
            $this->step = self::APPROVER;
        }
    }

    public function confirmReason(): void
    {
        $this->validate(
            ['note' => ['required', 'string', 'max:'.VoidData::NOTE_MAX]],
            attributes: ['note' => __('terminal.tickets.void.note')],
        );

        $this->step = self::APPROVER;
    }

    public function chooseApprover(string $staffId): void
    {
        $approver = StaffMember::query()->find($staffId);

        if ($approver === null || ! $approver->can(StaffMember::VOID_ORDERS)) {
            return;
        }

        $this->approverId = $staffId;
        $this->pin = '';
        $this->message = null;
        $this->step = self::PIN;
    }

    public function press(string $digit, CheckPin $checkPin, VoidTicketLine $voidLine, TillSession $session): void
    {
        $approver = $this->approverId === null ? null : StaffMember::query()->find($this->approverId);
        $voider = $session->staff();

        if ($this->step !== self::PIN || $approver === null || $voider === null || ! ctype_digit($digit) || strlen($digit) !== 1) {
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

        try {
            $voided = $voidLine->handle($voider, $approver, $this->ticketId, $this->lineId, $this->quantity, VoidReason::from((string) $this->reason), $this->note);
        } catch (TicketRefused $e) {
            $this->message = __("terminal.tickets.refused.{$e->reason}");
            $this->step = self::REASON;

            return;
        }

        // No slip until kitchen printing exists: the till only prints at the counter (Daniel, T6).
        session()->flash('notice', __('terminal.tickets.void.taken_off', ['quantity' => $voided->quantity, 'item' => $voided->name, 'ticket' => $voided->ticketTitle]));
        $this->backToTicket();
    }

    public function backspace(): void
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    /** One step back; from the first step, back to the ticket. */
    public function back(): void
    {
        $this->message = null;
        $this->pin = '';
        $first = ($this->line()?->remainingQuantity() ?? 1) > 1 ? self::QUANTITY : self::REASON;

        match (true) {
            $this->step === self::PIN => $this->step = self::APPROVER,
            $this->step === self::APPROVER => $this->step = self::REASON,
            $this->step === self::REASON && $first === self::QUANTITY => $this->step = self::QUANTITY,
            default => $this->backToTicket(),
        };
    }

    public function render(): View
    {
        $line = $this->line();

        return view('livewire.take-line-off', [
            'line' => $line,
            'ticket' => Ticket::query()->find($this->ticketId),
            'left' => $line?->remainingQuantity() ?? 0,
            'reasons' => VoidReason::forTicketLines(),
            // Who may approve: managers and owners on this till's staff list.
            'approvers' => StaffMember::query()->orderBy('name')->get()->filter(fn (StaffMember $m) => $m->can(StaffMember::VOID_ORDERS))->values(),
            'approver' => $this->approverId === null ? null : StaffMember::query()->find($this->approverId),
        ]);
    }

    /** The line, while its ticket is unpaid and something of it is left. */
    private function line(): ?TicketLine
    {
        $ticket = Ticket::query()->unpaid()->with('lines')->find($this->ticketId);
        $line = $ticket?->lines->firstWhere('id', $this->lineId);

        return $line instanceof TicketLine && $line->sync_status !== TicketLine::REFUSED && $line->remainingQuantity() > 0 ? $line : null;
    }

    private function backToTicket(): void
    {
        $this->redirectRoute('home', ['ticket' => $this->ticketId]);
    }
}
