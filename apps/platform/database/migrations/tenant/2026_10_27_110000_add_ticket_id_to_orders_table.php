<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ticket a sale paid (table service, T4b). No foreign key: tickets belong to
        // the Verticals module, and a till may sync the sale before the ticket.
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('ticket_id')->nullable()->after('staff_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
