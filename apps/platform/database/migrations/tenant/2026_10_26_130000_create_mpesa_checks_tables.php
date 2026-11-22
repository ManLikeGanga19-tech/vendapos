<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each M-Pesa code a till typed for a sale, and what Safaricom's confirmations say about it (T3d).
        Schema::create('mpesa_code_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->uuid('branch_id');
            $table->string('mpesa_code', 10)->index();
            $table->unsignedBigInteger('amount_cents');
            // pending → confirmed, or amount_mismatch / unconfirmed / reused (each also an open check).
            $table->string('status', 16)->index();
            $table->foreignId('mpesa_confirmation_id')->nullable()->constrained('mpesa_confirmations')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('checked_at')->nullable();

            $table->unique(['order_id', 'mpesa_code']);
        });

        // When a synced sale named this push as one of its tenders; a paid push nobody claims is checked.
        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('claimed_at')->nullable()->after('completed_at');
        });

        // What the owner should look at: typed codes Safaricom never confirmed, amounts that
        // differ, codes used twice, pushes paid with no sale. Resolved by a person, never deleted.
        Schema::create('mpesa_checks', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 24)->index();
            // One open check per problem, however often it is found again.
            $table->string('dedupe_key', 120)->unique();
            $table->uuid('branch_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->string('mpesa_code', 10)->nullable();
            $table->uuid('payment_id')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
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
        Schema::dropIfExists('mpesa_checks');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });

        Schema::dropIfExists('mpesa_code_claims');
    }
};
