<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets platform admins with an authenticator app into the admin panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');
    $admin->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('makes platform admins set up an authenticator app first', function () {
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');

    $this->actingAs($admin)->get('/admin')->assertRedirectContains('multi-factor-authentication');
});

it('stores the authenticator secret and recovery codes encrypted', function () {
    $admin = User::factory()->create();
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $admin->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

    $row = DB::table('users')->where('id', $admin->id)->first();

    expect($row->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($row->app_authentication_recovery_codes)->not->toContain('code-one')
        ->and($admin->fresh()?->getAppAuthenticationRecoveryCodes())->toBe(['code-one', 'code-two']);
});

it('keeps every other user out of the admin panel', function (?string $role) {
    $user = User::factory()->create();
    if ($role !== null) {
        $user->assignRole($role);
    }

    $this->actingAs($user)->get('/admin')->assertForbidden();
})->with([null, 'owner', 'manager', 'cashier', 'waiter']);

it('refuses everyone outside local development too', function () {
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('grants the platform-admin role from the command line', function () {
    $user = User::factory()->create(['email' => 'ops@danamo.test']);

    $this->artisan('users:grant-admin', ['email' => 'ops@danamo.test'])->assertSuccessful();
    $this->artisan('users:grant-admin', ['email' => 'nobody@danamo.test'])->assertFailed();

    expect($user->fresh()?->hasRole('platform-admin'))->toBeTrue();
});
