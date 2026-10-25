<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per document VendaPOS owes KRA for an order: the sale, and a credit note if it is voided.
        Schema::create('etims_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // No foreign key: the Etims module owns this table, not the orders.
            $table->uuid('order_id');
            $table->uuid('branch_id');
            // VendaPOS\Core\Enums\EtimsDocumentType
            $table->string('document_type', 16);
            // VendaPOS\Core\Enums\EtimsStatus
            $table->string('status', 16);
            // The order as its outbox event described it; the document is built from this at send time.
            $table->jsonb('order_snapshot');
            // What KRA returned once accepted (e.g. FDN, QR, invoice ID).
            $table->jsonb('receipt')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'document_type']);
            $table->index(['status', 'next_attempt_at']);
        });

        // Every failed attempt, kept for the Admin panel (architecture §6.4). Resolved once the document goes through.
        Schema::create('etims_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('submission_id')->constrained('etims_submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('error_code', 64);
            $table->text('error_message');
            $table->timestampTz('failed_at');
            $table->timestampTz('resolved_at')->nullable();

            $table->index(['submission_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etims_failures');
        Schema::dropIfExists('etims_submissions');
    }
};
