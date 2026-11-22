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
        // A ticket is paid once (Daniel, 2026-10-30). One the server has can be seen, and
        // paid, by other tills, so paying it needs the server's hold; one only this till
        // has ever had can be paid offline.
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('on_server')->default(false);
        });

        DB::table('tickets')->where('sync_status', '!=', 'pending')->update(['on_server' => true]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('on_server');
        });
    }
};
