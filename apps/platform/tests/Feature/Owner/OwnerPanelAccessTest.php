<?php

declare(strict_types=1);

use App\Domain\Tenancy\Actions\IssueOwnerSetupLink;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffSetupLink;
use App\Domain\Tenancy\Models\Tenant;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Owner\Auth\Login;
use App\Filament\Owner\Auth\SetUpAccount;
use App\Http\Middleware\EnsureSessionBelongsToTenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant] = tenantWithBranch();
    $this->tenant->update(['owner_name' => 'Achieng Otieno']);
    $this->tenant->domains()->create(['domain' => 'mama.localhost']);

    [$this->other] = tenantWithBranch();
    $this->other->domains()->create(['domain' => 'other.localhost']);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** Sets up an owner in the tenant who has chosen a username and password. */
function ownerOf(Tenant $tenant, string $username = 'achieng'): Staff
{
    return $tenant->run(function () use ($username) {
        $staff = Staff::create(['name' => 'Achieng Otieno', 'username' => $username, 'password' => 'correct-horse-9']);
        $staff->assignRole('owner');

        return $staff;
    });
}

/** Livewire tests skip HTTP middleware, so start the tenant and panel by hand. */
function inOwnerPanel(Tenant $tenant): void
{
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('owner'));
}

it('answers 404 on a domain no tenant owns', function () {
    $this->get('http://nosuchshop.localhost/login')->assertNotFound();
    $this->get('http://nosuchshop.localhost/')->assertNotFound();
});

it('serves the owner sign-in on the tenant’s domain and asks for a username', function () {
    $this->get('http://mama.localhost/login')
        ->assertOk()
        ->assertSee(__('owner.auth.username'));
});

it('loads the panel’s styles and scripts from the global assets on tenant domains', function () {
    $this->get('http://mama.localhost/login')
        ->assertOk()
        ->assertSee('http://mama.localhost/css/filament/filament/app.css', false)
        ->assertDontSee('/tenancy/assets/', false);
});

it('keeps the admin panel off tenant domains and the owner panel off central ones', function () {
    $this->get('http://mama.localhost/admin/login')->assertNotFound();
    $this->get('http://localhost/login')->assertRedirect('/admin');
});

it('opens a set-up link once and only on its own tenant', function () {
    $token = $this->tenant->run(fn () => app(IssueOwnerSetupLink::class)->handle('Achieng Otieno', 'ops@vendapos.test'));

    $this->get("http://other.localhost/setup/{$token}")->assertOk()->assertSee(__('owner.setup.link_closed'));
    $this->get("http://mama.localhost/setup/{$token}")->assertOk()->assertSee('Achieng Otieno')->assertDontSee(__('owner.setup.link_closed'));
});

it('lets the owner choose a username and password, then signs them in', function () {
    $token = $this->tenant->run(fn () => app(IssueOwnerSetupLink::class)->handle('Achieng Otieno', 'ops@vendapos.test'));
    inOwnerPanel($this->tenant);

    Livewire::test(SetUpAccount::class, ['token' => $token])
        ->fillForm(['username' => '  Achieng.O ', 'password' => 'correct-horse-9', 'passwordConfirmation' => 'correct-horse-9'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $owner = Staff::sole();
    expect($owner->username)->toBe('achieng.o')
        ->and(Filament::auth()->id())->toBe($owner->id)
        ->and(StaffSetupLink::open()->count())->toBe(0);

    // A used link shows the "expired" message instead of the form.
    Filament::auth()->logout();
    Livewire::test(SetUpAccount::class, ['token' => $token])->assertSee(__('owner.setup.link_closed'));
});

it('rejects badly formed and already taken usernames', function (string $username, string $error) {
    // A manager already uses "taken"; the link below is for a new owner.
    $this->tenant->run(fn () => Staff::create(['name' => 'Juma', 'username' => 'taken', 'password' => 'correct-horse-9'])->assignRole('manager'));
    $token = $this->tenant->run(fn () => app(IssueOwnerSetupLink::class)->handle('Achieng Otieno', 'ops@vendapos.test'));
    inOwnerPanel($this->tenant);

    $component = Livewire::test(SetUpAccount::class, ['token' => $token])
        ->fillForm(['username' => $username, 'password' => 'correct-horse-9', 'passwordConfirmation' => 'correct-horse-9'])
        ->call('save')
        ->assertHasFormErrors(['username']);

    expect($component->errors()->get('data.username'))->toBe([__($error)])
        ->and(Staff::where('username', 'achieng')->exists())->toBeFalse();
})->with([
    ['ab', 'owner.setup.username_format'],
    ['has space', 'owner.setup.username_format'],
    ['émile', 'owner.setup.username_format'],
    [' TAKEN ', 'owner.setup.username_taken'],
]);

it('lets an owner keep their username when a new link resets their password', function () {
    ownerOf($this->tenant);
    $token = $this->tenant->run(fn () => app(IssueOwnerSetupLink::class)->handle('Achieng Otieno', 'ops@vendapos.test'));
    inOwnerPanel($this->tenant);

    Livewire::test(SetUpAccount::class, ['token' => $token])
        ->assertSchemaStateSet(['username' => 'achieng'])
        ->fillForm(['password' => 'new-horse-battery-1', 'passwordConfirmation' => 'new-horse-battery-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-horse-battery-1', (string) Staff::sole()->password))->toBeTrue();
});

it('signs staff in by username on their own tenant only', function () {
    ownerOf($this->tenant);
    inOwnerPanel($this->tenant);

    Livewire::test(Login::class)
        ->fillForm(['username' => 'Achieng', 'password' => 'correct-horse-9'])
        ->call('authenticate')
        ->assertHasNoFormErrors();
    expect(Filament::auth()->check())->toBeTrue();

    Filament::auth()->logout();
    tenancy()->end();
    inOwnerPanel($this->other);

    Livewire::test(Login::class)
        ->fillForm(['username' => 'achieng', 'password' => 'correct-horse-9'])
        ->call('authenticate')
        ->assertHasFormErrors(['username']);
    expect(Filament::auth()->check())->toBeFalse();
});

it('makes signed-in staff set up an authenticator app first', function () {
    $owner = ownerOf($this->tenant);

    $this->actingAs($owner, 'tenant')
        ->get('http://mama.localhost/')
        ->assertRedirectContains('multi-factor-authentication');
});

it('only lets active owners and managers who finished set-up into the panel', function () {
    $panel = Filament::getPanel('owner');

    $this->tenant->run(function () use ($panel) {
        $owner = Staff::create(['name' => 'A', 'username' => 'a', 'password' => 'correct-horse-9']);
        $owner->assignRole('owner');
        $manager = Staff::create(['name' => 'B', 'username' => 'b', 'password' => 'correct-horse-9']);
        $manager->assignRole('manager');
        $cashier = Staff::create(['name' => 'C', 'username' => 'c', 'password' => 'correct-horse-9']);
        $cashier->assignRole('cashier');
        $notSetUp = Staff::create(['name' => 'D']);
        $notSetUp->assignRole('owner');
        $inactive = Staff::create(['name' => 'E', 'username' => 'e', 'password' => 'correct-horse-9', 'is_active' => false]);
        $inactive->assignRole('owner');

        expect($owner->canAccessPanel($panel))->toBeTrue()
            ->and($manager->canAccessPanel($panel))->toBeTrue()
            ->and($cashier->canAccessPanel($panel))->toBeFalse()
            ->and($notSetUp->canAccessPanel($panel))->toBeFalse()
            ->and($inactive->canAccessPanel($panel))->toBeFalse()
            ->and($owner->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
    });
});

it('throws away a session that was started on another tenant’s domain', function () {
    $this->withSession([EnsureSessionBelongsToTenant::KEY => $this->tenant->id, 'marker' => 'from-mama'])
        ->get('http://other.localhost/login')
        ->assertOk()
        ->assertSessionMissing('marker')
        ->assertSessionHas(EnsureSessionBelongsToTenant::KEY, $this->other->id);
});

it('gives platform admins a one-time owner link from the tenant page', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create(['email' => 'ops@vendapos.test']);
    $admin->assignRole('platform-admin');
    $this->actingAs($admin);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->id])
        ->callAction('ownerLoginLink')
        ->assertNotified();

    expect(tenancy()->initialized)->toBeFalse();
    $this->tenant->run(function () {
        expect(Staff::role('owner')->sole()->name)->toBe('Achieng Otieno')
            ->and(StaffSetupLink::open()->sole()->issued_by)->toBe('ops@vendapos.test');
    });
});
