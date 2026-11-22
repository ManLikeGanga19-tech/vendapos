<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Observers;

use App\Domain\Tenancy\Actions\NotifyTills;
use App\Domain\Tenancy\Models\Staff;
use App\Infrastructure\Realtime\TillsShouldSync;

/**
 * Staff: a change to what tills hold (name, PIN, active) tells every till of
 * the business, so someone moved between branches leaves the old till too.
 * Panel-only fields (last login, two-factor secrets) do not. Role and branch
 * changes are pivot tables, so SaveStaff signals those itself.
 */
final class TellTillsStaffChanged
{
    private const array TILL_FIELDS = ['name', 'pin_hash', 'is_active'];

    public function __construct(private readonly NotifyTills $notifyTills) {}

    public function created(Staff $staff): void
    {
        $this->notify();
    }

    // Not saved() with wasRecentlyCreated: that flag stays true on the object, so a later save would count as new.
    public function updated(Staff $staff): void
    {
        if ($staff->wasChanged(self::TILL_FIELDS)) {
            $this->notify();
        }
    }

    public function deleted(Staff $staff): void
    {
        $this->notify();
    }

    private function notify(): void
    {
        $tenantId = tenant('id');

        if (is_string($tenantId)) {
            $this->notifyTills->ofTenant($tenantId, TillsShouldSync::STAFF);
        }
    }
}
