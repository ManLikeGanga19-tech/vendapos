<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exactly-once side effects (architecture §6.2): one row per event per job,
        // written in the same transaction as the job's effect.
        Schema::create('processed_events', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->string('handler');
            $table->timestampTz('processed_at');

            $table->primary(['event_id', 'handler']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
