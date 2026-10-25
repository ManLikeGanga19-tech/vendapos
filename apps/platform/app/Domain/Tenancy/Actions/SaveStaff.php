<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffChange;
use App\Domain\Tenancy\Support\StaffRules;
use App\Infrastructure\Realtime\TillsShouldSync;
use Illuminate\Support\Facades\DB;

/**
 * Adds a staff member, or changes one's name, role and branches, within what
 * the person asking looks after (StaffRules). Owners work everywhere and have
 * no branch rows; everyone else works at one branch at least.
 *
 * A role change that needs a PIN of a different length clears the PIN, so
 * the person gets a new one before they can use a till again.
 */
final class SaveStaff
{
    public function __construct(private readonly StaffRules $rules, private readonly NotifyTills $notifyTills) {}

    /**
     * @param  list<string>  $branchIds
     * @return string the staff member's ID
     */
    public function handle(Staff $actor, ?string $id, string $name, string $role, array $branchIds): string
    {
        $branchIds = array_values(array_unique($branchIds));

        if (! $actor->is_active) {
            throw new StaffChangeRefused(StaffChangeRefused::NOT_YOURS);
        }

        if (! in_array($role, $this->rules->rolesFor($actor), true)) {
            throw new StaffChangeRefused(StaffChangeRefused::ROLE_NOT_ALLOWED);
        }

        if ($role === 'owner') {
            $branchIds = [];
        } elseif ($branchIds === []) {
            throw new StaffChangeRefused(StaffChangeRefused::NEEDS_BRANCH);
        } elseif (array_diff($branchIds, $this->rules->branchIdsFor($actor)) !== []) {
            throw new StaffChangeRefused(StaffChangeRefused::BRANCH_NOT_ALLOWED);
        }

        return DB::transaction(function () use ($actor, $id, $name, $role, $branchIds) {
            // One staff change per tenant at a time, so the last-owner check can't race.
            DB::select("select pg_advisory_xact_lock(hashtext(current_schema() || ':tenancy.staff'))");

            $staff = $id === null ? null : Staff::query()->findOrFail($id);
            $previous = $staff === null ? null : [
                'name' => $staff->name,
                'role' => $staff->roleName(),
                'branch_ids' => $staff->branches()->pluck('branches.id')->map(fn (mixed $id): string => (string) $id)->sort()->values()->all(),
            ];

            if ($staff !== null) {
                $this->guardChange($actor, $staff, $role);
            }

            $staff ??= new Staff;
            $staff->name = trim($name);
            $staff->updated_at = now(); // tills re-read the list when anything about a person changes
            $staff->save();
            $staff->syncRoles([$role]);
            $staff->branches()->sync($branchIds);
            // Roles and branches are pivot rows the staff observer never sees; tills hear after commit.
            $this->notifyTills->ofTenant((string) tenant('id'), TillsShouldSync::STAFF);

            sort($branchIds);
            StaffChange::create([
                'staff_id' => $staff->id,
                'changed_by' => $actor->id,
                'action' => $previous === null ? StaffChange::ACTION_ADDED : StaffChange::ACTION_UPDATED,
                'details' => ['name' => $staff->name, 'role' => $role, 'branch_ids' => $branchIds, 'previous' => $previous],
            ]);

            $oldRole = $previous['role'] ?? null;
            if ($staff->pin_hash !== null && $oldRole !== null && Staff::pinLengthFor($oldRole) !== Staff::pinLengthFor($role)) {
                $staff->forceFill(['pin_hash' => null, 'pin_set_at' => null])->save();
                StaffChange::create(['staff_id' => $staff->id, 'changed_by' => $actor->id, 'action' => StaffChange::ACTION_PIN_CLEARED, 'details' => ['reason' => 'role_changed']]);
            }

            return $staff->id;
        });
    }

    private function guardChange(Staff $actor, Staff $staff, string $role): void
    {
        if (! $this->rules->canManage($actor, $staff)) {
            throw new StaffChangeRefused(StaffChangeRefused::NOT_YOURS);
        }

        if ($staff->roleName() === $role) {
            return;
        }

        if ($staff->is($actor)) {
            throw new StaffChangeRefused(StaffChangeRefused::SELF);
        }

        if ($staff->roleName() === 'owner' && $staff->is_active && self::activeOwners() <= 1) {
            throw new StaffChangeRefused(StaffChangeRefused::LAST_OWNER);
        }
    }

    /** Run under the staff lock. */
    public static function activeOwners(): int
    {
        return Staff::role('owner', 'tenant')->where('is_active', true)->count();
    }
}
