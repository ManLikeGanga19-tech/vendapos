<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Http\Middleware\UseTillLocale;

/**
 * Livewire restores the language a component was first drawn in on every
 * later update, so a component on screen when the language changes would
 * stay in the old one. Re-read the till's choice before each redraw.
 */
trait FollowsTillLanguage
{
    public function renderingFollowsTillLanguage(): void
    {
        UseTillLocale::apply();
    }
}
