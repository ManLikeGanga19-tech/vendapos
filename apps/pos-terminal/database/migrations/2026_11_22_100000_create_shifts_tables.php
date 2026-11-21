<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This till's drawer shifts (VendaPOS\Core\Data\ShiftData): written here first, synced after.
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Per till, strictly increasing: the Z report number.
            $table->unsignedInteger('number')->unique();
            $table->timestamp('opened_at');
            $table->uuid('opened_by');
            $table->json('opening_counts');
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->json('closing_counts')->nullable();
            // What this till expected at close; the server works its own out too.
            $table->bigInteger('expected_cents')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->string('note', 200)->nullable();
            // Whether the server has the opening, and the close once there is one.
            $table->string('sync_status', 16)->default('pending')->index();
            $table->boolean('close_synced')->default(false);
            $table->string('sync_reason', 64)->nullable();
            $table->json('sync_errors')->nullable();
            $table->timestamp('synced_at')->nullable();
        });

        // Cash that left the drawer other than change (VendaPOS\Core\Data\CashMovementData).
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_id')->constrained('shifts');
            $table->string('kind', 16);
            $table->unsignedBigInteger('amount_cents');
            $table->timestamp('moved_at');
            $table->uuid('staff_id');
            $table->uuid('approved_by')->nullable();
            $table->string('reason', 200)->nullable();
            $table->uuid('order_id')->nullable();
            $table->boolean('synced')->default(false)->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            // The drawer shift the sale's cash went into.
            $table->uuid('shift_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
        });
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
    }
};
