<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            // Generated on the terminal: the idempotency key.
            $table->uuid('id')->primary();
            // The sale it pays for. No foreign key: payments usually come before the order syncs.
            $table->uuid('order_id')->nullable()->index();
            // From the authenticated device, never from the payload.
            $table->uuid('branch_id');
            $table->uuid('device_id');
            // VendaPOS\Core\Enums\MobileMoneyProvider
            $table->string('provider', 16);
            // E.164, e.g. +254712345678
            $table->string('phone', 16);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            // VendaPOS\Core\Enums\PaymentStatus
            $table->string('status', 16)->index();
            // Secret part of this payment's callback URL; only its hash is stored centrally.
            $table->text('callback_token');
            // e.g. Daraja CheckoutRequestID. Unique per provider so one result can never credit twice.
            $table->string('provider_request_id', 64)->nullable();
            // e.g. M-Pesa receipt number, once paid.
            $table->string('provider_reference', 64)->nullable();
            $table->string('failure_code', 32)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_request_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
