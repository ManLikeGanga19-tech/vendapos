<?php

declare(strict_types=1);

namespace App\Filament\Owner\Concerns;

use App\Domain\Catalogue\Exceptions\MenuConflict;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use VendaPOS\Core\Enums\BusinessType;

/**
 * For the Owner panel's menu pages. The menu is shared by every branch, so
 * only owners change it; managers read it. Actions check this on the server
 * through authorize(), not only by hiding buttons.
 */
trait ManagesMenu
{
    use ScopesToStaffBranches;

    /**
     * A shop has products, a restaurant a menu (T7): the few words that say
     * "menu" read "products" for a business without the restaurant module.
     * The catalogue underneath is the same.
     */
    protected static function menuWord(string $key): string
    {
        $tenant = tenant();
        $shopOnly = $tenant instanceof Tenant && $tenant->moduleTypes() !== [] && ! $tenant->hasModule(BusinessType::Restaurant);

        return (string) __(($shopOnly ? 'owner.menu.shop.' : 'owner.menu.').$key);
    }

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
