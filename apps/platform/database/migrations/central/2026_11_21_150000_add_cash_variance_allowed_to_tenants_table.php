<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cash-up: how far a drawer's count may be off before closing it needs a manager's
        // PIN and a note. Per business, set by the owner; KES 100 unless changed (Daniel).
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_variance_allowed_cents')->default(10_000)->after('modules');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('cash_variance_allowed_cents');
        });
    }
};
