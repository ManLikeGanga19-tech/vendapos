<?php

declare(strict_types=1);

namespace App\Filament\Owner\Concerns;

use App\Domain\Catalogue\Exceptions\MenuConflict;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * For the Owner panel's menu pages. The menu is shared by every branch, so
 * only owners change it; managers read it. Actions check this on the server
 * through authorize(), not only by hiding buttons.
 */
trait ManagesMenu
{
    use ScopesToStaffBranches;

    protected function canManageMenu(): bool
    {
        return $this->staff()->hasRole('owner');
    }

    /** Runs a menu change; a clash keeps the form open with the reason. */
    protected function changeMenu(Action $action, Closure $change, string $notice): void
    {
        try {
            $change();
        } catch (MenuConflict $e) {
            Notification::make()->title(__('owner.menu.conflict.'.$e->reason))->danger()->send();
            $action->halt();
        }

        $this->flushCachedTableRecords();
        Notification::make()->title($notice)->success()->send();
    }
}
