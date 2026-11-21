<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A till drawer's shift (cash-up). Recorded on the till first and synced; the
        // till's own figures are kept beside the server's, which the server works out
        // again from the synced sales and movements and never takes from the till.
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('device_id');
            // Per till, strictly increasing; printed as the Z report number.
            $table->unsignedInteger('number');
            $table->timestampTz('opened_at');
            $table->uuid('opened_by');
            $table->jsonb('opening_counts');
            $table->bigInteger('float_cents');
            $table->timestampTz('closed_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->jsonb('closing_counts')->nullable();
            $table->bigInteger('counted_cents')->nullable();
            $table->bigInteger('till_expected_cents')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->string('note', 200)->nullable();
            // The server's own reckoning, filled in when the shift closes and again
            // whenever one of its sales arrives late.
            $table->bigInteger('cash_taken_cents')->nullable();
            $table->bigInteger('pay_outs_cents')->nullable();
            $table->bigInteger('drops_cents')->nullable();
            $table->bigInteger('void_refunds_cents')->nullable();
            $table->bigInteger('expected_cents')->nullable();
            $table->bigInteger('variance_cents')->nullable();
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['device_id', 'number']);
            $table->index(['branch_id', 'opened_at']);
            $table->index('closed_at');
        });

        // Cash that left a drawer during a shift other than change. Append-only.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->bigInteger('amount_cents');
            $table->timestampTz('moved_at');
            $table->uuid('staff_id');
            $table->uuid('approved_by')->nullable();
            $table->string('reason', 200)->nullable();
            // The voided sale a refund was for; no foreign key, it may sync later.
            $table->uuid('order_id')->nullable();
            $table->timestampTz('created_at');

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
    }
};
