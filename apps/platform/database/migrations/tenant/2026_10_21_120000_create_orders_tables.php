<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // Generated on the terminal: the idempotency key for sync.
            $table->uuid('id')->primary();
            // From the authenticated device, never from the payload.
            $table->uuid('branch_id');
            $table->uuid('device_id');
            $table->unsignedBigInteger('sequence_number');
            // VendaPOS\Core\Enums\OrderStatus
            $table->string('status', 16);
            $table->timestampTz('placed_at');
            // VendaPOS\Core\Enums\PaymentType
            $table->string('payment_type', 16);
            // Server-computed from the lines (VAT-inclusive prices, VAT per line).
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('vat_cents');
            $table->unsignedBigInteger('total_cents');
            // KRA eTIMS seam: null until the Etims module handles the order.
            $table->string('etims_status', 16)->nullable();
            $table->jsonb('etims_receipt')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'sequence_number']);
            $table->index(['branch_id', 'placed_at']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshot at the time of sale, so later catalogue edits never rewrite history.
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            // VendaPOS\Core\Enums\VatCategory
            $table->string('vat_category', 16);
            $table->unsignedBigInteger('line_total_cents');
            $table->unsignedBigInteger('vat_cents');
            $table->timestamps();

            $table->unique(['order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
