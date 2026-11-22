<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\FollowsTillLanguage;
use App\Models\SyncState;
use App\Staff\TillSession;
use App\Sync\SyncStatus;
use App\Sync\SyncTill;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Connection, last sync and which till this is; refreshed every few seconds.
 * It is on every screen, so it is also where "sync now" from the server is
 * handled (resources/js/realtime.js dispatches till-sync).
 */
final class StatusBar extends Component
{
    use FollowsTillLanguage;

    public const string TIMEZONE = 'Africa/Nairobi';

    /** Re-render straight away in the new language instead of at the next poll. */
    #[On('language-changed')]
    public function refreshLanguage(): void {}

    /**
     * The server says something this till holds changed. Sync now, then send
     * a removed till to pairing and someone no longer allowed to sign-in.
     */
    #[On('till-sync')]
    public function syncNow(SyncTill $sync, Till $till, TillSession $session): void
    {
        $wasSignedIn = $session->hasSignedIn();

        $sync->handle();

        if ($till->isRemoved()) {
            $session->signOut();
            $this->redirectRoute('pair');

            return;
        }

        if ($wasSignedIn && $session->staff() === null) {
            $this->redirectRoute('sign-in');

            return;
        }

        $this->dispatch('till-updated');
    }

    public function render(Till $till): View
    {
        return view('livewire.status-bar', [
            'identity' => $till->isPaired() ? $till->identity() : null,
            'status' => SyncStatus::current(),
            // Here, not in the page head: it refreshes with the bar, so a till paired a moment ago starts listening without a reload.
            'realtime' => $till->isPaired() ? SyncState::read(SyncTill::REALTIME) : null,
            'timezone' => self::TIMEZONE,
        ]);
    }
}
