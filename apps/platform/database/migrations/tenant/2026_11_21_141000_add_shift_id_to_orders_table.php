<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The drawer shift a sale was made in (cash-up). No foreign key: shifts belong
        // to the CashUp module, and a sale from a till older than cash-up has none.
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('shift_id')->nullable()->after('ticket_id');
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
