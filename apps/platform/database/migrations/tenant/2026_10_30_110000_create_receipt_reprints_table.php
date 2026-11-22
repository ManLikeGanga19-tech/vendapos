<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every receipt printed again at a till, for the owner's audit. Keyed by the
        // till's UUID so a resent batch records nothing twice.
        Schema::create('receipt_reprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->uuid('staff_id');
            $table->timestampTz('reprinted_at');
            $table->timestampTz('created_at');

            $table->index(['order_id', 'reprinted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_reprints');
    }
};
