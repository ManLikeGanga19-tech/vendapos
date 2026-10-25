<?php

declare(strict_types=1);

namespace App\Filament\Owner\Concerns;

use App\Domain\Tenancy\Models\Staff;
use Filament\Facades\Filament;

/**
 * For Owner panel pages and widgets: the branches the signed-in person may
 * see, narrowed to the dashboard's branch picker when it names one of them.
 * The picker's value comes from the browser, so it is only ever used to
 * narrow the person's own branches, never to widen them.
 */
trait ScopesToStaffBranches
{
    /** @var list<string>|null read once per request */
    private ?array $visibleBranchIds = null;

    protected function staff(): Staff
    {
        $staff = Filament::auth()->user();
        abort_unless($staff instanceof Staff, 403);

        return $staff;
    }

    /** @return list<string> */
    protected function visibleBranchIds(): array
    {
        return $this->visibleBranchIds ??= $this->staff()->visibleBranchIds();
    }

    /** @return list<string> */
    protected function selectedBranchIds(): array
    {
        $picked = property_exists($this, 'pageFilters') ? ($this->pageFilters['branch'] ?? null) : null;

        return is_string($picked) && in_array($picked, $this->visibleBranchIds(), true)
            ? [$picked]
            : $this->visibleBranchIds();
    }

    protected function businessToday(): string
    {
        return now((string) config('vendapos.business_timezone'))->toDateString();
    }
}
