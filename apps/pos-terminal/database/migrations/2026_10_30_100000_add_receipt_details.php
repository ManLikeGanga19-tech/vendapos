<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This till's code in its business ("T2"), from the server; null until the first sync tells it.
        Schema::table('till_identity', function (Blueprint $table) {
            $table->string('till_code', 16)->nullable();
        });

        // What the receipt says, kept as it was at the sale so a reprint matches the first print
        // even after the till is paired again or the ticket is gone.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receipt_number', 32)->nullable();
            $table->string('table_name', 20)->nullable();
            $table->string('ticket_label', 40)->nullable();
        });

        // Receipts printed again, waiting to reach the owner's audit on the server.
        Schema::create('receipt_reprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('staff_id');
            $table->timestamp('reprinted_at');
            $table->string('sync_status', 16)->default('pending')->index();
            $table->string('sync_reason', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_reprints');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['receipt_number', 'table_name', 'ticket_label']);
        });

        Schema::table('till_identity', function (Blueprint $table) {
            $table->dropColumn('till_code');
        });
    }
};
