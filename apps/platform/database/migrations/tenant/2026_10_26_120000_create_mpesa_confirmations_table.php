<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where Safaricom sends notice of money paid straight to the account (C2B), so
        // M-Pesa codes typed at the till can be confirmed (T3d).
        Schema::table('payment_accounts', function (Blueprint $table) {
            // Encrypted; its SHA-256 routes confirmations here (central c2b_routes).
            $table->text('c2b_token')->nullable();
            // pending, registered or failed; message says why it failed.
            $table->string('c2b_status', 16)->nullable();
            $table->string('c2b_message')->nullable();
            $table->timestampTz('c2b_registered_at')->nullable();
        });

        // Payments into an account, as Safaricom confirmed them. Never updated except
        // to record which sale they paid.
        Schema::create('mpesa_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            // M-Pesa's transaction code, as the customer's SMS shows it.
            $table->string('trans_id', 20)->unique();
            $table->string('trans_type', 32)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('business_short_code', 16);
            $table->string('bill_ref', 32)->nullable();
            // Masked by Safaricom (e.g. 25470****149); never the full number.
            $table->string('msisdn', 32)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->timestampTz('paid_at');
            $table->unsignedBigInteger('webhook_receipt_id')->nullable();
            $table->timestampTz('created_at');

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_confirmations');

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn(['c2b_token', 'c2b_status', 'c2b_message', 'c2b_registered_at']);
        });
    }
};
