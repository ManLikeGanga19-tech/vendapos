<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\PrintVoidSlipJob;
use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Models\StaffMember;
use App\Sales\SalesToday as Sales;
use App\Sales\SaleSummary;
use App\Sales\VoidRefused;
use App\Sales\VoidSale;
use App\Staff\CheckPin;
use App\Staff\PinOutcome;
use App\Staff\TillSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\VoidReason;

/**
 * Today's sales, to find one and void it: the cashier picks the sale and a
 * reason, then a manager or owner approves with their PIN, every time, even
 * when they are the one signed in. Online it lists every till of the branch;
 * offline only this till's own sales.
 */
final class SalesToday extends Component
{
    use FollowsTillLanguage;
    use NeedsSignedInStaff;

    public const string LIST = 'list';

    public const string SALE = 'sale';

    public const string REASON = 'reason';

    public const string APPROVER = 'approver';

    public const string PIN = 'pin';

    public const string DONE = 'done';

    public string $step = self::LIST;

    public string $search = '';

    public ?string $saleId = null;

    public ?string $reason = null;

    public string $note = '';

    public ?string $approverId = null;

    public string $pin = '';

    public ?string $message = null;

    /** @var array{receipt: ?string, cash_cents: int, mpesa_cents: int}|null */
    public ?array $done = null;

    public function mount(TillSession $session): void
    {
        abort_unless($session->can(StaffMember::TAKE_PAYMENTS), 403);
    }

    public function select(string $saleId): void
    {
        $this->saleId = $saleId;
        $this->message = null;
        $this->step = self::SALE;
    }

    public function startVoid(Sales $sales): void
    {
        $sale = $this->saleId === null ? null : $sales->find($this->saleId);

        if ($sale === null || $sale->isVoided()) {
            return;
        }

        $this->reset('reason', 'note', 'approverId', 'pin', 'message');
        $this->step = self::REASON;
    }

    public function pickReason(string $reason): void
    {
        if (VoidReason::tryFrom($reason) === null) {
            return;
        }

        $this->reason = $reason;
        $this->message = null;

        // "Other" waits for its note; the rest go straight on.
        if (! VoidReason::from($reason)->needsNote()) {
            $this->step = self::APPROVER;
        }
    }

    public function confirmReason(): void
    {
        $this->validate(
            ['note' => ['required', 'string', 'max:'.VoidData::NOTE_MAX]],
            attributes: ['note' => __('terminal.sales.note')],
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

    public function press(string $digit, CheckPin $checkPin, VoidSale $voidSale, TillSession $session): void
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
            $voided = $voidSale->handle($voider, $approver, (string) $this->saleId, VoidReason::from((string) $this->reason), $this->note);
        } catch (VoidRefused $e) {
            $this->message = __("terminal.sales.refused.{$e->reason}");
            $this->step = self::SALE;

            return;
        }

        PrintVoidSlipJob::dispatch($voided);
        $this->done = ['receipt' => $voided->receiptNumber, 'cash_cents' => $voided->cashCents, 'mpesa_cents' => $voided->mpesaCents];
        $this->step = self::DONE;
    }

    public function backspace(): void
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    /** One step back; from the list, back to the sale screen. */
    public function back(): void
    {
        $this->message = null;
        $this->pin = '';

        match ($this->step) {
            self::PIN => $this->step = self::APPROVER,
            self::APPROVER => $this->step = self::REASON,
            self::REASON => $this->step = self::SALE,
            self::SALE => $this->step = self::LIST,
            default => $this->redirectRoute('home'),
        };
    }

    public function finish(): void
    {
        $this->reset('saleId', 'reason', 'note', 'approverId', 'pin', 'message', 'done');
        $this->step = self::LIST;
    }

    public function refresh(Sales $sales): void
    {
        $sales->forget();
    }

    public function render(Sales $sales, TillSession $session): View
    {
        $list = $sales->list();
        $sale = null;

        foreach ($list['sales'] as $candidate) {
            $sale = $candidate->id === $this->saleId ? $candidate : $sale;
        }

        return view('livewire.sales-today', [
            'online' => $list['online'],
            'sales' => array_values(array_filter($list['sales'], fn (SaleSummary $s) => $s->matches($this->search))),
            'sale' => $sale,
            'staffNames' => StaffMember::query()->pluck('name', 'id')->all(),
            // Who may approve: managers and owners on this till's staff list.
            'approvers' => StaffMember::query()->orderBy('name')->get()->filter(fn (StaffMember $m) => $m->can(StaffMember::VOID_ORDERS))->values(),
            'approver' => $this->approverId === null ? null : StaffMember::query()->find($this->approverId),
            'reasons' => VoidReason::cases(),
            'staff' => $session->staff(),
        ]);
    }
}
