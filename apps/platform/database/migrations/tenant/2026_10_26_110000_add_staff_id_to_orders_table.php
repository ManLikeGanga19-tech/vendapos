<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who made the sale (Daniel, T3c). Empty only for orders synced before tills sent it.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('staff_id')->nullable()->after('device_id')->constrained('staff')->restrictOnDelete();
            $table->index(['staff_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['staff_id', 'placed_at']);
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
