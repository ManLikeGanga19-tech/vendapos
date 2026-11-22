<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Voids the owner should look at (T5a-2): approved by someone who could not approve
        // voids, voided on a later business day, or a staff member voiding too many of their
        // sales in a day. Opened by the server, closed only by a person.
        Schema::create('void_checks', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24)->index();
            // One check per problem, however often it is found again.
            $table->string('dedupe_key', 120)->unique();
            $table->uuid('branch_id')->nullable()->index();
            $table->uuid('order_id')->nullable();
            $table->uuid('ticket_id')->nullable();
            // Whose voids or sales these are, and who approved.
            $table->uuid('staff_id')->nullable();
            $table->uuid('approved_by')->nullable();
            // The business day (Africa/Nairobi) it is about.
            $table->date('business_date')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->uuid('resolved_by')->nullable();
            $table->string('resolved_note')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('void_checks');
    }
};
