<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safaricom answered 429 (too many requests): ask about this payment again only after this.
        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('query_backoff_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('query_backoff_until');
        });
    }
};
