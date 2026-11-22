<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\FollowsTillLanguage;
use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Livewire\Concerns\PicksLanguage;
use App\Models\StaffMember;
use App\Staff\ChangeMyPin;
use App\Staff\PinChangeFailed;
use App\Staff\TillSession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Current PIN, new PIN, new PIN again. Finished PINs wait in the session, not
 * in the page, between steps; they are dropped once done or abandoned.
 */
final class ChangePin extends Component
{
    use FollowsTillLanguage;
    use NeedsSignedInStaff;
    use PicksLanguage;

    public const string CURRENT = 'current';

    public const string NEW = 'new';

    public const string CONFIRM = 'confirm';

    private const string HELD = 'pin_change';

    #[Locked]
    public string $step = self::CURRENT;

    public string $pin = '';

    public ?string $message = null;

    public function mount(): void
    {
        session()->forget(self::HELD);
    }

    public function press(string $digit, TillSession $session, ChangeMyPin $changeMyPin): void
    {
        $staff = $session->staff();

        if ($staff === null || ! ctype_digit($digit) || strlen($digit) !== 1) {
            return;
        }

        $this->pin .= $digit;
        $this->message = null;

        if (strlen($this->pin) < $staff->pin_length) {
            return;
        }

        $typed = $this->pin;
        $this->pin = '';

        match ($this->step) {
            self::CURRENT => $this->hold('current', $typed, self::NEW),
            self::NEW => $this->hold('new', $typed, self::CONFIRM),
            default => $this->finish($typed, $staff, $changeMyPin),
        };
    }

    public function backspace(): void
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    public function back(): void
    {
        session()->forget(self::HELD);
        $this->redirectRoute('home');
    }

    public function render(TillSession $session): View
    {
        return view('livewire.change-pin', ['staff' => $session->staff()]);
    }

    private function hold(string $which, string $pin, string $next): void
    {
        session()->put(self::HELD.'.'.$which, $pin);
        $this->step = $next;
    }

    private function finish(string $confirmation, StaffMember $staff, ChangeMyPin $changeMyPin): void
    {
        $current = (string) session(self::HELD.'.current');
        $new = (string) session(self::HELD.'.new');

        if (! hash_equals($new, $confirmation)) {
            session()->forget(self::HELD.'.new');
            $this->step = self::NEW;
            $this->message = __('terminal.pin_change.mismatch');

            return;
        }

        try {
            $changeMyPin->handle($staff, $current, $new);
        } catch (PinChangeFailed $e) {
            // A wrong current PIN or a lock starts over; a refused new PIN asks for another.
            $this->step = in_array($e->reason, ['wrong_pin', 'locked', 'offline', 'too_many_tries', 'not_on_server'], true) ? self::CURRENT : self::NEW;
            session()->forget($this->step === self::CURRENT ? self::HELD : self::HELD.'.new');
            $this->message = __("terminal.pin_change.reasons.{$e->reason}");

            return;
        }

        session()->forget(self::HELD);
        session()->flash('notice', __('terminal.pin_change.done'));
        $this->redirectRoute('home');
    }
}
