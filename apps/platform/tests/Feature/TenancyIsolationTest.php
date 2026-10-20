<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Domain\Tenancy\Models\Tenant;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_schemas_are_isolated(): void
    {
        // Create two tenants — this triggers schema creation and tenant migrations
        $tenantA = Tenant::create(['id' => 'tenant-a']);
        $tenantB = Tenant::create(['id' => 'tenant-b']);

        // Write a row in tenant A's schema
        tenancy()->initialize($tenantA);
        DB::table('example_items')->insert(['name' => 'Item A', 'price' => 1000, 'created_at' => now(), 'updated_at' => now()]);

        // Write a different row in tenant B's schema
        tenancy()->initialize($tenantB);
        DB::table('example_items')->insert(['name' => 'Item B', 'price' => 2000, 'created_at' => now(), 'updated_at' => now()]);

        // Prove tenant B cannot see tenant A's row
        $this->assertSame(['Item B'], DB::table('example_items')->pluck('name')->all());

        // Switch back to tenant A and prove it cannot see tenant B's row
        tenancy()->initialize($tenantA);
        $this->assertSame(['Item A'], DB::table('example_items')->pluck('name')->all());

        // Cleanup
        tenancy()->end();
        $tenantB->delete();
        $tenantA->delete();
    }

    public function test_tenant_schemas_have_separate_auto_increment(): void
    {
        $tenantA = Tenant::create(['id' => 'tenant-c']);
        $tenantB = Tenant::create(['id' => 'tenant-d']);

        // Both tenants start with id=1
        tenancy()->initialize($tenantA);
        DB::table('example_items')->insert(['name' => 'First in A', 'price' => 100, 'created_at' => now(), 'updated_at' => now()]);

        tenancy()->initialize($tenantB);
        DB::table('example_items')->insert(['name' => 'First in B', 'price' => 200, 'created_at' => now(), 'updated_at' => now()]);

        // Both should have id=1 — schemas are completely separate
        $this->assertSame(1, DB::table('example_items')->first()->id);

        tenancy()->initialize($tenantA);
        $this->assertSame(1, DB::table('example_items')->first()->id);

        // Cleanup
        tenancy()->end();
        $tenantB->delete();
        $tenantA->delete();
    }
}
