<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A sale this till made and then voided: who, who approved it, why and when. Sent with the order.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->string('void_reason', 32)->nullable();
            $table->string('void_note', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'voided_by', 'approved_by', 'void_reason', 'void_note']);
        });
    }
};
