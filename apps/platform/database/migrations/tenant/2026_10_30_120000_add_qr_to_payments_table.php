<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scan-to-pay (slice Q): a payment is a prompt to a phone or a QR code on the bill.
        // A QR payment has no phone; the customer's payment comes back on the C2B
        // confirmation with our reference as its account number.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('channel', 8)->default('push')->after('provider');
            $table->string('phone', 16)->nullable()->change();
            $table->string('reference', 12)->nullable()->unique()->after('phone');
            // Safaricom's QR image (base64 PNG), kept until the till has printed it.
            $table->text('qr_png')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['channel', 'reference', 'qr_png']);
        });
    }
};
