<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The branch's open tickets (table service): ones sent from this till, written here
        // first, and everyone else's, copied from the server.
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('table_id')->nullable();
            $table->string('table_name', 20)->nullable();
            $table->string('label', 40)->nullable();
            $table->uuid('staff_id');
            // open, bill_requested or closed.
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('bill_requested_at')->nullable();
            // The sale on this till that paid it.
            $table->uuid('order_id')->nullable();
            // pending until the server has this till's latest change; rejected keeps the reason.
            $table->string('sync_status', 20)->default('synced');
            $table->string('sync_reason')->nullable();
        });

        Schema::create('ticket_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id')->index();
            $table->uuid('item_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            // The menu price when sent; never repriced (Daniel, T4b).
            $table->unsignedBigInteger('unit_price_cents');
            $table->string('vat_category', 20);
            $table->uuid('staff_id');
            $table->timestamp('sent_at');
            // pending, synced, or refused (sent after the ticket was paid).
            $table->string('sync_status', 20)->default('synced');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('ticket_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ticket_id');
        });

        Schema::dropIfExists('ticket_lines');
        Schema::dropIfExists('tickets');
    }
};
