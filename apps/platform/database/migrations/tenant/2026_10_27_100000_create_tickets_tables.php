<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table service pack (T4b): open orders a waiter sends before anyone pays.
        // A ticket becomes an ordinary sale when paid; the core sale path never reads these.
        Schema::create('tickets', function (Blueprint $table) {
            // Client-generated: the idempotency key.
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            // The till that opened it; devices live in the central schema, as for orders.
            $table->uuid('device_id');
            $table->foreignUuid('staff_id')->constrained('staff')->restrictOnDelete();
            // Null for takeaway or counter tickets.
            $table->foreignUuid('dining_table_id')->nullable()->constrained('dining_tables')->restrictOnDelete();
            $table->string('label', 40)->nullable();
            // open → bill_requested → closed; new lines after the bill reopen it.
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('bill_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            // The sale that paid it. No foreign key: Orders is another module.
            $table->uuid('order_id')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('ticket_lines', function (Blueprint $table) {
            // Client-generated: resending a line adds nothing.
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->uuid('item_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            // The menu price when sent (Daniel, T4b); never repriced.
            $table->unsignedBigInteger('unit_price_cents');
            $table->string('vat_category', 20);
            $table->unsignedBigInteger('line_total_cents');
            $table->foreignUuid('staff_id')->constrained('staff')->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['ticket_id', 'sent_at']);
        });

        // A sale that paid a ticket, kept even when the ticket itself has not
        // reached the server yet (same till, offline), so it closes on arrival.
        Schema::create('ticket_closures', function (Blueprint $table) {
            $table->uuid('ticket_id')->primary();
            $table->uuid('order_id');
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamp('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_closures');
        Schema::dropIfExists('ticket_lines');
        Schema::dropIfExists('tickets');
    }
};
