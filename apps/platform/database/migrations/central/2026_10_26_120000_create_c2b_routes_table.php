<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central map from a confirmation URL token's SHA-256 to one tenant's payment
        // account. Safaricom's C2B confirmations carry no tenant; this is how they find one.
        Schema::create('c2b_routes', function (Blueprint $table) {
            $table->char('token_hash', 64)->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('payment_account_id');
            $table->timestampTz('created_at');

            $table->unique(['tenant_id', 'payment_account_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('c2b_routes');
    }
};
