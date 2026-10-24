<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Domain\Tenancy\Actions\SetStaffLocale;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/** The Owner panel's language switch: saved on the person, then back where they were. */
final class SwitchLanguage
{
    public function __invoke(string $locale, SetStaffLocale $setLocale): RedirectResponse
    {
        abort_unless(in_array($locale, Tenant::LOCALES, true), 404);

        $staff = Filament::auth()->user();
        abort_unless($staff instanceof Staff, 403);

        $setLocale->handle($staff, $locale);

        return redirect()->back(fallback: Filament::getPanel('owner')->getUrl() ?? '/');
    }
}
