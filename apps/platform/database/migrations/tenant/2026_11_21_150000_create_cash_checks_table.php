<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cash-up problems for the owner: a drawer off by more than allowed, the till's
        // and the server's figures disagreeing, an approver who may not close shifts, a
        // shift left open, or a sale made outside a shift. Opened by the server; the two
        // that depend on late sales settle themselves when the figures come right, and
        // every other one is closed only by a person.
        Schema::create('cash_checks', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24)->index();
            // One check per problem, however often it is found again.
            $table->string('dedupe_key', 120)->unique();
            $table->uuid('branch_id')->nullable()->index();
            $table->uuid('shift_id')->nullable()->index();
            $table->uuid('order_id')->nullable();
            $table->uuid('staff_id')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->bigInteger('amount_cents')->nullable();
            // The business day (Africa/Nairobi) it is about.
            $table->date('business_date')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 16)->default('open')->index();
            // Null with a resolved status: settled by the server, not by a person.
            $table->uuid('resolved_by')->nullable();
            $table->string('resolved_note')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_checks');
    }
};
