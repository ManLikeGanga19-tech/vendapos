<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who voided a sale, who approved it and why (architecture §10: each order
        // records who approved any void). One per order: a sale is voided once.
        Schema::create('order_voids', function (Blueprint $table) {
            $table->foreignUuid('order_id')->primary()->constrained()->cascadeOnDelete();
            // The till the void was made on: not always the one that made the sale.
            $table->uuid('device_id');
            $table->timestampTz('voided_at');
            $table->uuid('voided_by');
            $table->uuid('approved_by');
            $table->string('reason', 32);
            $table->string('note', 200)->nullable();
            // What the server found when the void arrived. Never a reason to refuse it.
            $table->boolean('approver_allowed');
            $table->boolean('same_business_day');
            $table->timestampTz('created_at');

            $table->index('voided_at');
            $table->index('approved_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_voids');
    }
};
