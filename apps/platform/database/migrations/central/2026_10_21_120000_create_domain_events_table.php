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
        // The transactional outbox (architecture §6). Central, but written through
        // the tenant connection as public.domain_events so the event commits in
        // the same transaction as the order it describes.
        Schema::create('domain_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_type', 64);
            $table->string('tenant_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('device_id')->nullable();
            $table->uuid('aggregate_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->unsignedSmallInteger('schema_ver')->default(1);
            $table->jsonb('payload');
            $table->timestampTz('recorded_at')->useCurrent();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index('aggregate_id');
        });

        // Append-only at the database level: no code path may rewrite history.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION domain_events_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'domain_events is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER domain_events_no_update_delete
                BEFORE UPDATE OR DELETE ON domain_events
                FOR EACH ROW EXECUTE FUNCTION domain_events_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
        DB::unprepared('DROP FUNCTION IF EXISTS domain_events_append_only()');
    }
};
