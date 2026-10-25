<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Sync\SyncStatus;
use App\Till\Till;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/** Connection, last sync and which till this is; refreshed every few seconds. */
final class StatusBar extends Component
{
    public const string TIMEZONE = 'Africa/Nairobi';

    /** Re-render straight away in the new language instead of at the next poll. */
    #[On('language-changed')]
    public function refreshLanguage(): void {}

    public function render(Till $till): View
    {
        return view('livewire.status-bar', [
            'identity' => $till->isPaired() ? $till->identity() : null,
            'status' => SyncStatus::current(),
            'timezone' => self::TIMEZONE,
        ]);
    }
}
