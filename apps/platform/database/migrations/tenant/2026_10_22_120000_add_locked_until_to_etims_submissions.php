<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etims_submissions', function (Blueprint $table) {
            // Set while a worker is sending; kept apart from next_attempt_at so that
            // rescheduling a document (e.g. "Retry now") can never break a send in progress.
            $table->timestampTz('locked_until')->nullable()->after('next_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('etims_submissions', function (Blueprint $table) {
            $table->dropColumn('locked_until');
        });
    }
};
