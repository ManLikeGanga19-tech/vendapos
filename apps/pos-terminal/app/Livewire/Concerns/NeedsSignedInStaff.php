<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Staff\TillSession;

/**
 * For screens behind sign-in. The route middleware guards the page load;
 * this guards every tap on the page and counts it as activity. Background
 * polling (the status bar) is a different component and never counts.
 */
trait NeedsSignedInStaff
{
    public function hydrateNeedsSignedInStaff(): void
    {
        $session = app(TillSession::class);

        if ($session->staff() === null) {
            $this->redirectRoute('sign-in');

            return;
        }

        $session->touch();
    }
}
