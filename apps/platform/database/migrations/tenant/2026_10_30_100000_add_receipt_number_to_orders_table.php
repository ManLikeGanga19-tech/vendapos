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
        // The number printed on the slip ("T2-000123"): kept so owners can search by it.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receipt_number', 32)->nullable()->after('sequence_number');
        });

        // Sales synced before this: the till numbers live in the central devices table.
        $tills = DB::connection(config('tenancy.database.central_connection'))
            ->table('devices')
            ->where('tenant_id', tenant()?->getTenantKey())
            ->pluck('till_number', 'id');

        foreach ($tills as $deviceId => $tillNumber) {
            DB::table('orders')->where('device_id', $deviceId)->update([
                'receipt_number' => DB::raw("'T{$tillNumber}-' || LPAD(sequence_number::text, GREATEST(6, LENGTH(sequence_number::text)), '0')"),
            ]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
