<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\ListStaff;
use App\Domain\Tenancy\Actions\ResetStaffPin;
use App\Domain\Tenancy\Actions\SaveStaff;
use App\Domain\Tenancy\Actions\SetStaffActive;
use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffChange;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Owner\Pages\StaffMembers;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant, $this->nyali] = tenantWithBranch();

    $this->tenant->run(function () {
        $this->westlands = Branch::create(['name' => 'Westlands', 'business_type' => BusinessType::Restaurant]);

        $person = function (string $name, string $role, array $branches = [], ?string $username = null): Staff {
            $staff = Staff::create(['name' => $name, 'username' => $username, 'password' => $username ? 'correct-horse-9' : null]);
            $staff->assignRole($role);
            $staff->branches()->attach(array_map(fn (Branch $branch) => $branch->id, $branches));

            return $staff;
        };

        $this->owner = $person('Owner', 'owner', username: 'owner');
        $this->manager = $person('Manager', 'manager', [$this->nyali], 'manager');
        $this->nyaliCashier = $person('Achieng', 'cashier', [$this->nyali]);
        $this->westlandsWaiter = $person('Baraka', 'waiter', [$this->westlands]);
        $this->sharedCashier = $person('Chebet', 'cashier', [$this->nyali, $this->westlands]);
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function signInToStaff(Tenant $tenant, Staff $staff): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    test()->actingAs($staff, 'tenant');
}

it('never issues PINs anyone would guess first', function (string $pin, bool $obvious) {
    expect(ResetStaffPin::isObvious($pin))->toBe($obvious);
})->with([
    ['0000', true], ['1234', true], ['4321', true], ['8901', true], ['123456', true], ['987654', true],
    ['1235', false], ['2580', false], ['1212', false], ['904173', false],
]);

it('makes PINs of the role’s length, and stores only a hash', function () {
    $this->tenant->run(function () {
        $cashierPin = app(ResetStaffPin::class)->handle($this->owner, $this->nyaliCashier->id);
        $managerPin = app(ResetStaffPin::class)->handle($this->owner, $this->manager->id);

        $cashier = $this->nyaliCashier->fresh();
        expect($cashierPin)->toMatch('/^\d{4}$/')
            ->and($managerPin)->toMatch('/^\d{6}$/')
            ->and($cashier?->pin_hash)->not->toContain($cashierPin)
            ->and(Hash::check($cashierPin, (string) $cashier?->pin_hash))->toBeTrue()
            ->and($cashier?->pin_set_at)->not->toBeNull()
            ->and(StaffChange::query()->where('staff_id', $cashier?->id)->value('action'))->toBe(StaffChange::ACTION_PIN_RESET)
            ->and(json_encode(StaffChange::query()->get()->toArray()))->not->toContain($cashierPin);
    });
});

it('lets owners add anyone, anywhere, and records it', function () {
    $this->tenant->run(function () {
        $id = app(SaveStaff::class)->handle($this->owner, null, ' Daudi ', 'manager', [$this->westlands->id]);
        $partner = app(SaveStaff::class)->handle($this->owner, null, 'Partner', 'owner', [$this->nyali->id]);

        $daudi = Staff::query()->findOrFail($id);
        expect($daudi->name)->toBe('Daudi')
            ->and($daudi->roleName())->toBe('manager')
            ->and($daudi->branches()->pluck('branches.id')->all())->toBe([$this->westlands->id])
            ->and(Staff::query()->findOrFail($partner)->branches()->count())->toBe(0) // owners work everywhere
            ->and(StaffChange::query()->where('staff_id', $id)->first()?->toArray())->toMatchArray([
                'changed_by' => $this->owner->id,
                'action' => StaffChange::ACTION_ADDED,
            ]);
    });
});

it('keeps managers to cashiers and waiters in their own branches', function () {
    $this->tenant->run(function () {
        $save = app(SaveStaff::class);

        $id = $save->handle($this->manager, null, 'Wanjiru', 'waiter', [$this->nyali->id]);
        $save->handle($this->manager, $this->nyaliCashier->id, 'Achieng O.', 'waiter', [$this->nyali->id]);
        expect(Staff::query()->findOrFail($id)->roleName())->toBe('waiter')
            ->and($this->nyaliCashier->fresh()?->roleName())->toBe('waiter');

        $refused = fn (Closure $change, string $reason) => expect($change)->toThrow(StaffChangeRefused::class, $reason);
        $refused(fn () => $save->handle($this->manager, null, 'X', 'manager', [$this->nyali->id]), StaffChangeRefused::ROLE_NOT_ALLOWED);
        $refused(fn () => $save->handle($this->manager, null, 'X', 'cashier', [$this->westlands->id]), StaffChangeRefused::BRANCH_NOT_ALLOWED);
        $refused(fn () => $save->handle($this->manager, null, 'X', 'cashier', []), StaffChangeRefused::NEEDS_BRANCH);
        // Someone who also works at a branch the manager doesn't run is out of reach.
        $refused(fn () => $save->handle($this->manager, $this->sharedCashier->id, 'X', 'cashier', [$this->nyali->id]), StaffChangeRefused::NOT_YOURS);
        $refused(fn () => $save->handle($this->manager, $this->westlandsWaiter->id, 'X', 'waiter', [$this->nyali->id]), StaffChangeRefused::NOT_YOURS);
        $refused(fn () => app(ResetStaffPin::class)->handle($this->manager, $this->westlandsWaiter->id), StaffChangeRefused::NOT_YOURS);
        $refused(fn () => app(ResetStaffPin::class)->handle($this->manager, $this->owner->id), StaffChangeRefused::NOT_YOURS);
        $refused(fn () => app(SetStaffActive::class)->handle($this->manager, $this->sharedCashier->id, false), StaffChangeRefused::NOT_YOURS);

        expect($this->sharedCashier->fresh()?->is_active)->toBeTrue()
            ->and($this->westlandsWaiter->fresh()?->pin_hash)->toBeNull();
    });
});

it('never lets anyone change their own role or switch themselves off', function () {
    $this->tenant->run(function () {
        expect(fn () => app(SetStaffActive::class)->handle($this->owner, $this->owner->id, false))->toThrow(StaffChangeRefused::class, StaffChangeRefused::SELF)
            ->and(fn () => app(SaveStaff::class)->handle($this->owner, $this->owner->id, 'Owner', 'manager', [$this->nyali->id]))->toThrow(StaffChangeRefused::class, StaffChangeRefused::SELF);

        // Only an active owner changes an owner, so another active owner always remains.
        $partner = Staff::query()->findOrFail(app(SaveStaff::class)->handle($this->owner, null, 'Partner', 'owner', []));
        app(SetStaffActive::class)->handle($partner, $this->owner->id, false);
        expect(fn () => app(SetStaffActive::class)->handle($partner, $partner->id, false))->toThrow(StaffChangeRefused::class, StaffChangeRefused::SELF)
            ->and(fn () => app(SaveStaff::class)->handle($this->owner->fresh(), $partner->id, 'Partner', 'manager', [$this->nyali->id]))->toThrow(StaffChangeRefused::class, StaffChangeRefused::NOT_YOURS)
            ->and(SaveStaff::activeOwners())->toBe(1);
    });
});

it('clears the PIN when a new role needs a longer one', function () {
    $this->tenant->run(function () {
        app(ResetStaffPin::class)->handle($this->owner, $this->nyaliCashier->id);
        app(SaveStaff::class)->handle($this->owner, $this->nyaliCashier->id, 'Achieng', 'waiter', [$this->nyali->id]);
        expect($this->nyaliCashier->fresh()?->pin_hash)->not->toBeNull(); // same length, PIN kept

        app(SaveStaff::class)->handle($this->owner, $this->nyaliCashier->id, 'Achieng', 'manager', [$this->nyali->id]);
        expect($this->nyaliCashier->fresh()?->pin_hash)->toBeNull()
            ->and(StaffChange::query()->where('staff_id', $this->nyaliCashier->id)->latest('id')->value('action'))->toBe(StaffChange::ACTION_PIN_CLEARED);
    });
});

it('shows managers only the people who share a branch with them', function () {
    $this->tenant->run(function () {
        $rows = collect(app(ListStaff::class)->handle($this->manager));

        expect($rows->pluck('name')->all())->toBe(['Achieng', 'Chebet', 'Manager'])
            ->and($rows->firstWhere('name', 'Achieng')['manageable'] ?? null)->toBeTrue()
            ->and($rows->firstWhere('name', 'Chebet')['manageable'] ?? null)->toBeFalse()
            ->and($rows->firstWhere('name', 'Manager')['is_you'] ?? null)->toBeTrue()
            ->and(collect(app(ListStaff::class)->handle($this->owner))->pluck('name')->all())->toBe(['Achieng', 'Baraka', 'Chebet', 'Manager', 'Owner']);
    });
});

it('adds a person from the panel and shows their PIN once', function () {
    signInToStaff($this->tenant, $this->owner);

    Livewire::test(StaffMembers::class)
        ->assertSee('Baraka')
        ->callAction(TestAction::make('addStaff')->table(), ['name' => 'Wanjiru', 'role' => 'waiter', 'branch_ids' => [$this->westlands->id]])
        ->assertActionMounted('showPin')
        ->assertMountedActionModalSee('PIN for Wanjiru');

    $wanjiru = Staff::query()->where('name', 'Wanjiru')->firstOrFail();
    expect($wanjiru->roleName())->toBe('waiter')
        ->and($wanjiru->pin_hash)->not->toBeNull();
});

it('gives a new PIN and switches people off from the panel', function () {
    signInToStaff($this->tenant, $this->owner);

    Livewire::test(StaffMembers::class)
        ->callAction(TestAction::make('resetPin')->table($this->nyaliCashier->id))
        ->assertActionMounted('showPin');

    Livewire::test(StaffMembers::class)
        ->callAction(TestAction::make('deactivate')->table($this->westlandsWaiter->id))
        ->assertDontSee('Baraka')
        ->filterTable('status', 'inactive')
        ->assertSee('Baraka');

    expect($this->nyaliCashier->fresh()?->pin_hash)->not->toBeNull()
        ->and($this->westlandsWaiter->fresh()?->is_active)->toBeFalse();
});

it('hides what a manager may not do, and refuses it when forced', function () {
    signInToStaff($this->tenant, $this->manager);

    Livewire::test(StaffMembers::class)
        ->assertDontSee('Baraka')
        ->assertActionVisible(TestAction::make('resetPin')->table($this->nyaliCashier->id))
        ->assertActionHidden(TestAction::make('resetPin')->table($this->sharedCashier->id))
        ->assertActionHidden(TestAction::make('deactivate')->table($this->manager->id));

    foreach ([$this->sharedCashier->id, $this->westlandsWaiter->id, $this->owner->id] as $id) {
        try {
            Livewire::test(StaffMembers::class)->callAction(TestAction::make('resetPin')->table($id));
        } catch (Throwable) {
            // Refused either way.
        }
        try {
            Livewire::test(StaffMembers::class)->callAction(TestAction::make('deactivate')->table($id));
        } catch (Throwable) {
        }
    }

    expect(Staff::query()->whereNotNull('pin_hash')->count())->toBe(0)
        ->and(Staff::query()->where('is_active', false)->count())->toBe(0);
});

it('never shows or changes another tenant’s staff', function () {
    [$other] = tenantWithBranch();
    $theirs = $other->run(function () {
        $staff = Staff::create(['name' => 'Their cashier']);
        $staff->assignRole('owner');

        return $staff;
    });

    signInToStaff($this->tenant, $this->owner);
    Livewire::test(StaffMembers::class)->assertSee('Achieng')->assertDontSee('Their cashier');

    try {
        Livewire::test(StaffMembers::class)->callAction(TestAction::make('resetPin')->table($theirs->id));
    } catch (Throwable) {
        // Not a record here.
    }

    tenancy()->end();
    expect($other->run(fn () => $theirs->fresh()?->pin_hash))->toBeNull();
});
