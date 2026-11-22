<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scan-to-pay on the bill (slice Q). The sale that will pay the ticket gets its UUID
        // when the first QR is asked for, since an M-Pesa payment names its sale; the latest
        // bill's QR payment is the one the cashier waits on. This till only.
        Schema::table('tickets', function (Blueprint $table) {
            $table->uuid('sale_id')->nullable();
            $table->uuid('qr_payment_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sale_id', 'qr_payment_id']);
        });
    }
};
