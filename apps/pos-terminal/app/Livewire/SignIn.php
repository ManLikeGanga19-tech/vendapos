<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PicksLanguage;
use App\Models\Item;
use App\Models\StaffMember;
use App\Staff\CheckPin;
use App\Staff\PinOutcome;
use App\Staff\TillSession;
use App\Sync\SyncTill;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Tap your name, then type your PIN. Checked on the till, so it works offline. */
final class SignIn extends Component
{
    use PicksLanguage;

    #[Locked]
    public ?string $staffId = null;

    public string $pin = '';

    public ?string $message = null;

    public function mount(Till $till, TillSession $session): void
    {
        if (! $till->isPaired()) {
            $this->redirectRoute('pair');
        } elseif ($session->staff() !== null) {
            $this->redirectRoute('home');
        }
    }

    public function choose(string $staffId): void
    {
        if (StaffMember::query()->whereKey($staffId)->exists()) {
            $this->staffId = $staffId;
            $this->pin = '';
            $this->message = null;
        }
    }

    public function back(): void
    {
        $this->reset('staffId', 'pin', 'message');
    }

    public function press(string $digit, CheckPin $checkPin, TillSession $session): void
    {
        $staff = $this->chosen();

        if ($staff === null || ! ctype_digit($digit) || strlen($digit) !== 1) {
            return;
        }

        $this->pin .= $digit;
        $this->message = null;

        // No OK button: the PIN is checked as soon as it is long enough.
        if (strlen($this->pin) < $staff->pin_length) {
            return;
        }

        $outcome = $checkPin->handle($staff, $this->pin);
        $this->pin = '';

        if ($outcome->isAccepted()) {
            $session->signIn($staff);
            $this->redirectRoute('home');

            return;
        }

        $this->message = $outcome->result === PinOutcome::LOCKED
            ? __('terminal.sign_in.locked', ['time' => $outcome->lockedUntil?->setTimezone(StatusBar::TIMEZONE)->format('H:i')])
            : trans_choice('terminal.sign_in.wrong', $outcome->triesLeft, ['count' => $outcome->triesLeft]);
    }

    public function backspace(): void
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    public function syncNow(SyncTill $sync, Till $till): void
    {
        $sync->handle();

        if (! $till->isPaired()) {
            $this->redirectRoute('pair');
        }
    }

    public function render(Till $till): View
    {
        return view('livewire.sign-in', [
            'identity' => $till->identity(),
            'staff' => StaffMember::query()->orderBy('name')->get(),
            'chosen' => $this->chosen(),
            'downloadPending' => Item::query()->doesntExist() && session('download_pending', false),
        ]);
    }

    private function chosen(): ?StaffMember
    {
        return $this->staffId === null ? null : StaffMember::query()->find($this->staffId);
    }
}
