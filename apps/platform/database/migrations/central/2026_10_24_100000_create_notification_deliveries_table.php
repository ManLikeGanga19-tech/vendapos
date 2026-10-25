<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per message handed to a provider (or refused by it), so the
        // admin can prove an owner was told, e.g. before a suspended tenant is purged.
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('notification');
            $table->string('channel', 16);
            $table->string('recipient');
            $table->string('status', 16);
            $table->string('provider_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
        });

        // The language the owner is written to in; English until set.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('locale', 2)->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::dropIfExists('notification_deliveries');
    }
};
