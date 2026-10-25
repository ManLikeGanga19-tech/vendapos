<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-time codes a new till types to pair itself. Central, because the
        // till doesn't know its tenant until the code tells the server. Only
        // the SHA-256 of a code is kept; the code is shown once to its issuer.
        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            // Lives in the tenant's schema, so no foreign key across schemas.
            $table->uuid('branch_id');
            $table->string('device_name');
            $table->char('code_hash', 64)->unique();
            // Staff ID (tenant schema) of whoever asked for the code.
            $table->uuid('issued_by');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->uuid('device_id')->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairing_codes');
    }
};
