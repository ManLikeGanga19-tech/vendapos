<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use InvalidArgumentException;

/** Saves the Owner panel language a person picked. Run inside the tenant's context. */
final class SetStaffLocale
{
    public function handle(Staff $staff, string $locale): void
    {
        if (! in_array($locale, Tenant::LOCALES, true)) {
            throw new InvalidArgumentException("Unsupported locale {$locale}.");
        }

        $staff->forceFill(['locale' => $locale])->save();
    }
}
