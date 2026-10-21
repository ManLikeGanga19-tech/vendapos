<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every provider callback, logged raw before anything trusts it (CLAUDE.md §7).
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->char('token_hash', 64)->nullable();
            // Filled only when the URL token matched a callback route.
            $table->string('tenant_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->string('ip', 45);
            $table->text('body');
            // received, processed or rejected
            $table->string('status', 16);
            $table->string('note')->nullable();
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();

            $table->index(['tenant_id', 'payment_id']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
