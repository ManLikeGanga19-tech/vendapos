<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function () {
    [$this->tenant] = tenantWithBranch();
    $this->tenant->domains()->create(['domain' => 'mama.localhost']);

    $this->tenant->run(function () {
        $make = function (string $username): Staff {
            $staff = Staff::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'correct-horse-9']);
            $staff->assignRole('owner');
            $staff->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

            return $staff;
        };
        $this->owner = $make('owner');
        $this->partner = $make('partner');
    });
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('speaks the business’s language until a person picks their own', function () {
    $this->tenant->update(['locale' => 'sw']);

    $this->get('http://mama.localhost/login')->assertOk()->assertSee('Jina la mtumiaji');
    tenancy()->end();

    $this->actingAs($this->owner, 'tenant')
        ->get('http://mama.localhost/menu/items')
        ->assertOk()
        ->assertSee('Ongeza bidhaa')
        ->assertSee('English')
        ->assertDontSee('Kiswahili');
});

it('saves the language a person picks, for them alone', function () {
    $this->actingAs($this->owner, 'tenant')
        ->from('http://mama.localhost/menu/items')
        ->post('http://mama.localhost/language/sw')
        ->assertRedirect('http://mama.localhost/menu/items');
    tenancy()->end();

    expect($this->tenant->run(fn () => $this->owner->fresh()?->locale))->toBe('sw');

    $this->actingAs($this->owner, 'tenant')->get('http://mama.localhost/menu/items')->assertSee('Ongeza bidhaa')->assertSee('English');
    tenancy()->end();

    // Another person, in their own session.
    $this->flushSession();
    $this->actingAs($this->partner, 'tenant')->get('http://mama.localhost/menu/items')->assertSee('Add item')->assertSee('Kiswahili');
});

it('refuses languages it does not have, and anyone signed out', function () {
    $this->actingAs($this->owner, 'tenant')->post('http://mama.localhost/language/fr')->assertNotFound();
    tenancy()->end();

    auth('tenant')->logout();
    $this->post('http://mama.localhost/language/sw')->assertRedirect('http://mama.localhost/login');
    tenancy()->end();

    expect($this->tenant->run(fn () => $this->owner->fresh()?->locale))->toBeNull();
});

it('keeps the admin panel in English', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');
    $admin->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

    app()->setLocale('sw');
    $this->actingAs($admin)->get('/admin')->assertOk();

    expect(app()->getLocale())->toBe('en');
});
