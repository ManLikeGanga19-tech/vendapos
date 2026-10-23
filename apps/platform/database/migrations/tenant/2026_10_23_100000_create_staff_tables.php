<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everyone who works for the business: owners and managers sign in to
        // the Owner panel with a username; cashiers and waiters get PINs later.
        // UUIDs, so a staff ID from one tenant never matches a row in another.
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // Lower-case. Null until the person picks one on their set-up link.
            $table->string('username', 30)->nullable()->unique();
            $table->string('password')->nullable();
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Branches a manager may see. Owners see every branch and have no rows.
        Schema::create('branch_staff', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->primary(['branch_id', 'staff_id']);
        });

        // One-time links to choose a username and password. Only the SHA-256 of
        // the token is stored; the token itself is shown once to whoever issued it.
        Schema::create('staff_setup_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('issued_by');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });

        // spatie/laravel-permission tables for this tenant's staff. The central
        // copies hold platform admins only. model_id is a UUID to match staff.
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        DB::table('roles')->insert(array_map(
            fn (string $role) => ['name' => $role, 'guard_name' => 'tenant', 'created_at' => $now, 'updated_at' => $now],
            ['owner', 'manager', 'cashier', 'waiter'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('staff_setup_links');
        Schema::dropIfExists('branch_staff');
        Schema::dropIfExists('staff');
    }
};
