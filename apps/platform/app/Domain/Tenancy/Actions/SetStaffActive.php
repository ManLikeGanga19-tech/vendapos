<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffChange;
use App\Domain\Tenancy\Support\StaffRules;
use Illuminate\Support\Facades\DB;

/**
 * Switches a staff member off (no till PIN, no Owner panel) or back on.
 * People are never deleted: orders will point at them. Nobody switches
 * themselves off, and the business always keeps one active owner.
 */
final class SetStaffActive
{
    public function __construct(private readonly StaffRules $rules) {}

    public function handle(Staff $actor, string $staffId, bool $active): void
    {
        DB::transaction(function () use ($actor, $staffId, $active) {
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':tenancy.staff'))");

            $staff = Staff::query()->findOrFail($staffId);

            if (! $this->rules->canManage($actor, $staff)) {
                throw new StaffChangeRefused(StaffChangeRefused::NOT_YOURS);
            }

            if ($staff->is_active === $active) {
                return;
            }

            if ($staff->is($actor)) {
                throw new StaffChangeRefused(StaffChangeRefused::SELF);
            }

            if (! $active && $staff->roleName() === 'owner' && SaveStaff::activeOwners() <= 1) {
                throw new StaffChangeRefused(StaffChangeRefused::LAST_OWNER);
            }

            $staff->forceFill(['is_active' => $active])->save();
            StaffChange::create([
                'staff_id' => $staff->id,
                'changed_by' => $actor->id,
                'action' => $active ? StaffChange::ACTION_REACTIVATED : StaffChange::ACTION_DEACTIVATED,
            ]);
        });
    }
}
