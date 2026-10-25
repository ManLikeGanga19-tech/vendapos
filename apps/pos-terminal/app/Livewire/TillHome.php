<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\NeedsSignedInStaff;
use App\Livewire\Concerns\PicksLanguage;
use App\Models\Item;
use App\Models\StaffMember;
use App\Staff\TillSession;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Stand-in home screen after sign-in until the sale screen (T3) replaces it. */
final class TillHome extends Component
{
    use NeedsSignedInStaff;
    use PicksLanguage;

    public function signOut(TillSession $session): void
    {
        $session->signOut();
        $this->redirectRoute('sign-in');
    }

    public function render(Till $till, TillSession $session): View
    {
        return view('livewire.till-home', [
            'identity' => $till->identity(),
            'staff' => $session->staff(),
            'itemCount' => Item::query()->where('is_active', true)->count(),
            'staffCount' => StaffMember::query()->count(),
        ]);
    }
}
