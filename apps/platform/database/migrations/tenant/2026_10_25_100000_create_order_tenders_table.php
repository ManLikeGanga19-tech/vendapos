<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How each sale was paid, with amounts (approved by Daniel, M2). Immutable once synced.
        Schema::create('order_tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // VendaPOS\Core\Enums\TenderMethod
            $table->string('method', 16);
            $table->unsignedBigInteger('amount_cents');
            // Cash only.
            $table->unsignedBigInteger('tendered_cents')->nullable();
            $table->unsignedBigInteger('change_cents')->nullable();
            // M-Pesa push only; one payment pays one sale.
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            // Typed M-Pesa code only; checked against Safaricom's confirmations later.
            $table->string('mpesa_reference', 10)->nullable()->index();
            $table->timestamps();

            $table->unique(['order_id', 'position']);
        });

        // Derived from the tenders; a cancelled sale has none, so it has no payment type.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_type', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tenders');
    }
};
