<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_all_five_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $expected = ['cashier', 'manager', 'owner', 'platform-admin', 'waiter'];

        $this->assertSame($expected, Role::pluck('name')->sort()->values()->all());
    }

    public function test_role_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(5, Role::count());
    }
}
