<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Who suspended, reactivated or purged a tenant, and why. Never
        // deleted: it outlives the tenant's data.
        Schema::create('tenant_status_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('action', 16);
            $table->string('reason', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_status_changes');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['status', 'suspended_at', 'suspension_reason', 'purge_after']);
        });
    }
};
