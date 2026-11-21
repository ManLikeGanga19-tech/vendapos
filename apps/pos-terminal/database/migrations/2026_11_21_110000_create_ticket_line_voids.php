<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sent ticket lines taken off before payment, all or part, with a manager's PIN
        // (T5c): made here and sent with the ticket, or copied from the server.
        Schema::create('ticket_line_voids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id')->index();
            $table->uuid('ticket_line_id')->index();
            $table->unsignedInteger('quantity');
            $table->timestamp('voided_at');
            $table->uuid('voided_by');
            $table->uuid('approved_by');
            $table->string('reason', 32);
            $table->string('note', 200)->nullable();
            // pending until the server has it; refused when the server would not take it off.
            $table->string('sync_status', 20)->default('synced');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_line_voids');
    }
};
