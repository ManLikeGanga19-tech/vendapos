<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sent ticket lines taken off before payment, all or part, approved with a manager's
        // PIN (T5c). Only ever added, like the lines: a line's quantity is never edited.
        Schema::create('ticket_line_voids', function (Blueprint $table) {
            // Client-generated: resending a void takes nothing more off.
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignUuid('ticket_line_id')->constrained('ticket_lines')->cascadeOnDelete();
            // The till it was made on.
            $table->uuid('device_id');
            $table->unsignedInteger('quantity');
            $table->timestampTz('voided_at');
            $table->foreignUuid('voided_by')->constrained('staff')->restrictOnDelete();
            $table->foreignUuid('approved_by')->constrained('staff')->restrictOnDelete();
            $table->string('reason', 32);
            $table->string('note', 200)->nullable();
            // What the server found when it arrived. Never a reason to refuse it.
            $table->boolean('approver_allowed');
            $table->timestampTz('created_at');

            $table->index(['ticket_id', 'voided_at']);
            $table->index('approved_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_line_voids');
    }
};
