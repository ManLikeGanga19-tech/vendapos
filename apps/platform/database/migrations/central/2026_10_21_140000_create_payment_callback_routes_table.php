<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider callbacks arrive with no tenant subdomain and no device token.
        // The secret token in the callback URL is the only way in: its SHA-256
        // maps to exactly one tenant and payment. Written through the tenant
        // connection as public.payment_callback_routes, in the payment's transaction.
        Schema::create('payment_callback_routes', function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->string('tenant_id');
            $table->uuid('payment_id');
            $table->string('provider', 16);
            $table->timestampTz('created_at');

            $table->unique(['tenant_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callback_routes');
    }
};
