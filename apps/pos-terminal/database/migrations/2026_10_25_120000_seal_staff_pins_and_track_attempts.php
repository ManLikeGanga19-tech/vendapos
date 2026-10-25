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
        // PIN hashes are now encrypted by the OS keyring as well: a 4-digit PIN
        // has only 10,000 possibilities, so a bare hash on a stolen disk could
        // be guessed. Hashes already here are unsealed, so drop them; the next
        // staff sync downloads the list again.
        DB::table('staff')->delete();
        DB::table('sync_state')->where('key', 'staff.synced_at')->delete();

        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('pin_hash', 'pin_hash_sealed');
        });

        // Wrong PINs in a row per person. Kept in the database, not the session,
        // so restarting the app does not lift a lock.
        Schema::create('pin_attempts', function (Blueprint $table) {
            $table->uuid('staff_id')->primary();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_attempts');

        DB::table('staff')->delete();

        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('pin_hash_sealed', 'pin_hash');
        });
    }
};
