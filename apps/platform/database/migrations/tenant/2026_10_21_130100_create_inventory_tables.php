<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On-hand quantity per branch. Signed: offline sales are never refused for
        // stock (server wins, approved by Daniel), so a level may go negative.
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id');
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->bigInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'item_id']);
        });

        // Append-only ledger; stock_levels is the running sum of these rows.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id');
            $table->foreignUuid('item_id')->constrained('items')->restrictOnDelete();
            $table->bigInteger('quantity');
            // StockMovement::REASON_* (sale, void)
            $table->string('reason', 16);
            $table->uuid('order_id')->nullable()->index();
            $table->uuid('event_id');
            $table->timestampTz('created_at');

            $table->unique(['event_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }
};
