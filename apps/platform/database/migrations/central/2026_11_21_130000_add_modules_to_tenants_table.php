<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The business modules a tenant has (T7): VendaPOS\Core\Enums\BusinessType values. Each
        // branch runs one of them; only platform staff add modules. Existing tenants are filled
        // in from their branches by tenants:backfill-modules.
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
