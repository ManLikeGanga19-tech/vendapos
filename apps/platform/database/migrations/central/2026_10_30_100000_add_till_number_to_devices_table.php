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
        // Each till's number within its business (T1, T2, …), printed in its receipt
        // numbers. Given once at pairing and never reused, even after a till is revoked.
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('till_number')->nullable()->after('name');
        });

        // Tills paired before this get numbers in the order they were paired.
        DB::statement(<<<'SQL'
            UPDATE devices SET till_number = numbered.n
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY tenant_id ORDER BY created_at, id) AS n
                FROM devices
            ) AS numbered
            WHERE devices.id = numbered.id
            SQL);

        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('till_number')->nullable(false)->change();
            $table->unique(['tenant_id', 'till_number']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'till_number']);
            $table->dropColumn('till_number');
        });
    }
};
