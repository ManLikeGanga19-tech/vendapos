<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Admin\Resources\TenantResource\Pages\TenantBranches;
use App\Filament\Owner\Pages\Branches;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use VendaPOS\Core\Enums\BusinessType;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['email' => 'ops@vendapos.test']);
    $this->admin->assignRole('platform-admin');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** @return array<string, mixed> */
function newTenantForm(array $overrides = []): array
{
    return array_merge([
        'id' => 'mamaoliech',
        'name' => 'Mama Oliech',
        'owner_name' => 'Oliech Achieng',
        'owner_phone' => '0712 345 678',
        'owner_email' => null,
        'locale' => 'en',
        'modules' => ['restaurant', 'shop'],
        'first_branch_name' => 'Kilimani',
        'first_branch_type' => 'restaurant',
    ], $overrides);
}

it('sets up a business with its modules and first branch, from the admin panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateTenant::class)->fillForm(newTenantForm())->call('create')->assertHasNoFormErrors();

    $tenant = Tenant::query()->findOrFail('mamaoliech');
    expect($tenant->moduleTypes())->toBe([BusinessType::Restaurant, BusinessType::Shop])
        ->and($tenant->run(fn () => Branch::query()->sole()->only(['name', 'business_type'])))
        ->toBe(['name' => 'Kilimani', 'business_type' => BusinessType::Restaurant]);
});

it('only gives modules that are built, and opens the first branch with one of them', function (array $overrides, string $field) {
    $this->actingAs($this->admin);

    Livewire::test(CreateTenant::class)->fillForm(newTenantForm($overrides))->call('create')->assertHasFormErrors([$field]);

    expect(Tenant::query()->find('mamaoliech'))->toBeNull();
})->with([
    'a module coming soon' => [['modules' => ['bar'], 'first_branch_type' => 'bar'], 'modules'],
    'no module' => [['modules' => []], 'modules'],
    'a branch type it does not have' => [['modules' => ['shop'], 'first_branch_type' => 'restaurant'], 'first_branch_type'],
]);

it('adds modules later, but never takes away one a branch still runs', function () {
    [$tenant] = tenantWithBranch(BusinessType::Restaurant);
    $tenant->update(['name' => 'Mama Oliech', 'owner_name' => 'Oliech Achieng', 'owner_phone' => '+254712345678']);
    $this->actingAs($this->admin);

    Livewire::test(EditTenant::class, ['record' => $tenant->id])
        ->fillForm(['modules' => ['shop']])
        ->call('save')
        ->assertHasFormErrors(['modules']);

    Livewire::test(EditTenant::class, ['record' => $tenant->id])
        ->fillForm(['modules' => ['restaurant', 'general']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tenant->refresh()->moduleTypes())->toBe([BusinessType::Restaurant, BusinessType::General]);
});

it('lets platform staff add a branch running one of the business’s modules', function () {
    [$tenant] = tenantWithBranch(BusinessType::Restaurant);
    $tenant->update(['modules' => ['restaurant', 'shop']]);
    $this->actingAs($this->admin);

    Livewire::test(TenantBranches::class, ['record' => $tenant->id])
        ->callAction(TestAction::make('addBranch')->table(), ['name' => 'Westlands duka', 'business_type' => 'shop'])
        ->assertHasNoActionErrors();

    expect($tenant->run(fn () => Branch::query()->where('name', 'Westlands duka')->sole()->business_type))->toBe(BusinessType::Shop)
        ->and(tenancy()->initialized)->toBeFalse();
});

it('opens a branch only with a module the business has, built, and a new name', function () {
    [$tenant] = tenantWithBranch(BusinessType::Restaurant);

    $tenant->run(function () {
        $create = app(CreateBranch::class);

        expect(fn () => $create->handle('Duka', BusinessType::Shop))->toThrow(ValidationException::class)
            ->and(fn () => $create->handle('Baa', BusinessType::Bar))->toThrow(ValidationException::class)
            ->and(fn () => $create->handle('  ', BusinessType::Restaurant))->toThrow(ValidationException::class)
            ->and(fn () => $create->handle(strtoupper(Branch::query()->sole()->name), BusinessType::Restaurant))->toThrow(ValidationException::class)
            ->and($create->handle(' Nyali 2 ', BusinessType::Restaurant)->name)->toBe('Nyali 2');
    });
});

it('lets the owner add branches from their modules, and keeps managers out', function () {
    [$tenant, $branch] = tenantWithBranch(BusinessType::Restaurant);
    $tenant->update(['modules' => ['restaurant', 'shop']]);

    [$owner, $manager] = $tenant->run(function () use ($branch) {
        $make = function (string $username, string $role) use ($branch): Staff {
            $staff = Staff::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'correct-horse-9']);
            $staff->assignRole($role);
            $staff->branches()->attach($branch->id);

            return $staff;
        };

        return [$make('owner', 'owner'), $make('manager', 'manager')];
    });

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));

    $this->actingAs($manager, 'tenant');
    expect(Branches::canAccess())->toBeFalse();

    $this->actingAs($owner, 'tenant');
    Livewire::test(Branches::class)
        ->assertSee($branch->name)
        ->assertActionExists(TestAction::make('add')->table())
        ->callAction(TestAction::make('add')->table(), ['name' => 'Kilimani duka', 'business_type' => 'shop'])
        ->assertHasNoActionErrors()
        ->assertSee('Kilimani duka');

    Livewire::test(Branches::class)
        ->callAction(TestAction::make('add')->table(), ['name' => 'Baa', 'business_type' => 'bar'])
        ->assertHasActionErrors(['business_type']);

    expect(Branch::query()->count())->toBe(2);
});

it('fills in the modules of businesses made before modules existed', function () {
    [$tenant] = tenantWithBranch(BusinessType::Shop);
    $tenant->update(['modules' => null]);

    $this->artisan('tenants:backfill-modules')->assertSuccessful();
    $this->artisan('tenants:backfill-modules')->expectsOutputToContain('0 tenant(s)');

    expect($tenant->refresh()->moduleTypes())->toBe([BusinessType::Shop]);
});

it('never shows or opens branches in another business', function () {
    [$mine, $myBranch] = tenantWithBranch(BusinessType::Restaurant);
    [$theirs, $theirBranch] = tenantWithBranch(BusinessType::Restaurant);
    $mine->update(['modules' => ['restaurant']]);
    $theirs->update(['modules' => ['restaurant', 'shop']]);

    $owner = $mine->run(function () use ($myBranch) {
        $staff = Staff::create(['name' => 'Owner', 'username' => 'owner', 'password' => 'correct-horse-9']);
        $staff->assignRole('owner');
        $staff->branches()->attach($myBranch->id);

        return $staff;
    });

    tenancy()->initialize($mine);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
    $this->actingAs($owner, 'tenant');

    // The other business has the shop module; this one does not, so it cannot borrow it.
    Livewire::test(Branches::class)
        ->assertSee($myBranch->name)
        ->assertDontSee($theirBranch->name)
        ->callAction(TestAction::make('add')->table(), ['name' => 'Duka', 'business_type' => 'shop'])
        ->assertHasActionErrors(['business_type']);

    tenancy()->end();

    expect($theirs->run(fn () => Branch::query()->pluck('name')->all()))->toBe([$theirBranch->name]);
});
