<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Observers;

use App\Domain\Tenancy\Actions\NotifyTills;
use App\Infrastructure\Realtime\TillsShouldSync;
use Illuminate\Database\Eloquent\Model;

/**
 * Menu items and categories: any save or delete in a tenant tells every
 * till of the business to sync its menu. The menu has one price for all
 * branches, so all tills hold it.
 */
final class TellTillsMenuChanged
{
    public function __construct(private readonly NotifyTills $notifyTills) {}

    public function saved(Model $model): void
    {
        $this->notify();
    }

    public function deleted(Model $model): void
    {
        $this->notify();
    }

    private function notify(): void
    {
        $tenantId = tenant('id');

        if (is_string($tenantId)) {
            $this->notifyTills->ofTenant($tenantId, TillsShouldSync::MENU);
        }
    }
}
