<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A ticket is paid once (Daniel, 2026-10-30): before a till takes payment for a
        // ticket the server holds it for that sale, so no other till can take payment too.
        Schema::table('tickets', function (Blueprint $table) {
            $table->uuid('held_for_order_id')->nullable();
            $table->uuid('held_by_device_id')->nullable();
            $table->timestampTz('held_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['held_for_order_id', 'held_by_device_id', 'held_until']);
        });
    }
};
