<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sales are written here first and synced later (CLAUDE.md §3.5); nothing waits for the network.
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Strictly increasing on this till; the server keeps it unique per device.
            $table->unsignedBigInteger('sequence_number')->unique();
            $table->uuid('staff_id');
            $table->string('status', 16);
            $table->timestamp('placed_at');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('vat_cents');
            $table->unsignedBigInteger('total_cents');
            // pending → synced, or rejected with the server's reason (kept for a person to look at).
            $table->string('sync_status', 16)->default('pending')->index();
            $table->string('sync_reason', 64)->nullable();
            $table->json('sync_errors')->nullable();
            $table->timestamp('synced_at')->nullable();
        });

        // Name, price and VAT are copied in: the menu may change or drop the item before the sale syncs.
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->uuid('item_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->string('vat_category', 16);
            $table->unsignedBigInteger('line_total_cents');

            $table->unique(['order_id', 'position']);
        });

        Schema::create('order_tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // VendaPOS\Core\Enums\TenderMethod
            $table->string('method', 16);
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('tendered_cents')->nullable();
            $table->unsignedBigInteger('change_cents')->nullable();
            $table->uuid('payment_uuid')->nullable();
            $table->string('mpesa_reference', 10)->nullable();

            $table->unique(['order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tenders');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
