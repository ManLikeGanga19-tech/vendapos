<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PicksLanguage;
use App\Models\Item;
use App\Models\StaffMember;
use App\Sync\SyncTill;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Stand-in home screen until staff PIN sign-in (T2) replaces it. */
final class TillReady extends Component
{
    use PicksLanguage;

    public function mount(Till $till): void
    {
        if (! $till->isPaired()) {
            $this->redirectRoute('pair');
        }
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
        $itemCount = Item::query()->where('is_active', true)->count();

        return view('livewire.till-ready', [
            'identity' => $till->identity(),
            'itemCount' => $itemCount,
            'staffCount' => StaffMember::query()->count(),
            'downloadPending' => $itemCount === 0 && session('download_pending', false),
        ]);
    }
}
