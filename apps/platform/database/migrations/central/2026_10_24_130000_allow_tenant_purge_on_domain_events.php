<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Still append-only: no UPDATE ever, and DELETE only of one tenant's
        // rows inside the purge transaction, which marks itself with
        // SET LOCAL vendapos.purge_tenant = '<tenant id>' (gone at commit).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION domain_events_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.tenant_id = current_setting('vendapos.purge_tenant', true) THEN
                    RETURN OLD;
                END IF;
                RAISE EXCEPTION 'domain_events is append-only';
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION domain_events_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'domain_events is append-only';
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }
};
