<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets platform admins into the admin panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('platform-admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
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
