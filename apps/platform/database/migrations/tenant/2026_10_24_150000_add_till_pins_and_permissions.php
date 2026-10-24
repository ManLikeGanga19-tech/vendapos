<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** What each role may do on a till. Tills check these offline; M2 records who did what. */
    private const ROLE_PERMISSIONS = [
        'waiter' => ['orders.take'],
        'cashier' => ['orders.take', 'payments.take', 'receipts.reprint'],
        'manager' => ['orders.take', 'payments.take', 'receipts.reprint', 'orders.void', 'orders.discount', 'drawer.open', 'shifts.close'],
        'owner' => ['orders.take', 'payments.take', 'receipts.reprint', 'orders.void', 'orders.discount', 'drawer.open', 'shifts.close'],
    ];

    public function up(): void
    {
        // Till PINs: a hash only. The PIN is generated and shown once to whoever set it.
        Schema::table('staff', function (Blueprint $table) {
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_set_at')->nullable();
        });

        // Append-only record of changes to people: added, role, branches, PIN reset, (de)activated.
        Schema::create('staff_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->uuid('changed_by')->nullable();
            // StaffChange::ACTION_*
            $table->string('action', 24);
            $table->jsonb('details')->nullable();
            $table->timestamp('created_at');
            $table->index(['staff_id', 'created_at']);
        });

        $now = now();
        $names = array_values(array_unique(array_merge(...array_values(self::ROLE_PERMISSIONS))));
        DB::table('permissions')->insert(array_map(
            fn (string $name) => ['name' => $name, 'guard_name' => 'tenant', 'created_at' => $now, 'updated_at' => $now],
            $names,
        ));

        $permissionIds = DB::table('permissions')->where('guard_name', 'tenant')->pluck('id', 'name');
        $roleIds = DB::table('roles')->where('guard_name', 'tenant')->pluck('id', 'name');

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            DB::table('role_has_permissions')->insert(array_map(
                fn (string $permission) => ['permission_id' => $permissionIds[$permission], 'role_id' => $roleIds[$role]],
                $permissions,
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = array_values(array_unique(array_merge(...array_values(self::ROLE_PERMISSIONS))));
        DB::table('permissions')->where('guard_name', 'tenant')->whereIn('name', $names)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('staff_changes');
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_set_at']);
        });
    }
};
