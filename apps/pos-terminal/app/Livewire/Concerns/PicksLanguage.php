<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Http\Middleware\UseTillLocale;
use App\Models\Preference;

trait PicksLanguage
{
    public function useLanguage(string $locale): void
    {
        if (! in_array($locale, UseTillLocale::LOCALES, true)) {
            return;
        }

        Preference::write(Preference::LOCALE, $locale);
        app()->setLocale($locale);
    }
}
