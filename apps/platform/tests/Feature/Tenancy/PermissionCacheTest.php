<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\StaffMayAtBranch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Production keeps one cache (Redis) for every business. The test default (array) gives each
    // tenant context a store of its own, which hides a shared key, so these tests share one store.
    config(['cache.default' => 'file']);
    app('cache')->forgetDriver('file');
    app(PermissionRegistrar::class)->initializeCache();
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** A manager at the business's first branch. */
function permissionCacheManager(Tenant $tenant, string $branchId): Staff
{
    return $tenant->run(function () use ($branchId) {
        $manager = Staff::create(['name' => 'Baraka']);
        $manager->assignRole('manager');
        $manager->branches()->attach($branchId);

        return $manager;
    });
}

it('keeps each business’s role permissions to itself, whatever a long-lived worker loaded before', function () {
    [$first, $firstBranch] = tenantWithBranch(BusinessType::Shop);
    [$second, $secondBranch] = tenantWithBranch(BusinessType::Shop);
    $firstManager = permissionCacheManager($first, $firstBranch->id);
    $secondManager = permissionCacheManager($second, $secondBranch->id);

    // Only the second business has this permission: another business's cached set would not know it.
    $second->run(function () {
        Permission::create(['name' => 'reports.view', 'guard_name' => 'tenant']);
        Role::findByName('manager', 'tenant')->givePermissionTo('reports.view');
    });

    // A long-lived queue or Octane worker that then loads permissions outside any business.
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(PermissionRegistrar::class)->getPermissions();

    $may = fn (Tenant $tenant, Staff $staff, string $permission, string $branchId) => $tenant->run(
        fn () => app(StaffMayAtBranch::class)->handle($staff->id, $permission, $branchId),
    );

    expect($may($first, $firstManager, 'shifts.close', $firstBranch->id))->toBeTrue()
        ->and($may($second, $secondManager, 'reports.view', $secondBranch->id))->toBeTrue()
        ->and($may($first, $firstManager, 'reports.view', $firstBranch->id))->toBeFalse()
        ->and($may($second, $secondManager, 'shifts.close', $secondBranch->id))->toBeTrue();
});
