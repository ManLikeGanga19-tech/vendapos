<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real columns so the admin can search and sort, and so suspension
        // notices (Slice H5) know whom to reach. Nullable: older tenants have none.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('owner_name')->nullable();
            $table->string('owner_phone', 16)->nullable(); // E.164
            $table->string('owner_email')->nullable();
        });

        // name and the timestamps used to live in the virtual `data` column too.
        DB::statement(<<<'SQL'
            UPDATE tenants SET
                name = data::jsonb ->> 'name',
                updated_at = COALESCE(updated_at, (data::jsonb ->> 'updated_at')::timestamp),
                data = (data::jsonb - 'name' - 'created_at' - 'updated_at')::json
            WHERE data IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE tenants SET data = (COALESCE(data::jsonb, '{}'::jsonb) || jsonb_build_object('name', name))::json
            WHERE name IS NOT NULL
            SQL);

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['name', 'owner_name', 'owner_phone', 'owner_email']);
        });
    }
};
