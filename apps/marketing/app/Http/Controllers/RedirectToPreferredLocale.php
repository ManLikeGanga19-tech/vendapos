<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends visitors who land on "/" to the language their browser prefers,
 * falling back to the first configured locale.
 */
class RedirectToPreferredLocale
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var list<string> $locales */
        $locales = array_keys(config('marketing.locales'));

        $locale = $request->getPreferredLanguage($locales) ?? $locales[0];

        return redirect()
            ->route('home', ['locale' => $locale])
            ->header('Vary', 'Accept-Language');
    }
}
