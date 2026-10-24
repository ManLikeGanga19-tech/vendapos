<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantExport;
use App\Infrastructure\Exports\StreamCipher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Dumps one tenant's schema with pg_dump (custom format), proves the dump
 * is complete and readable, then stores it encrypted on the private exports
 * disk for the retention period. Runs outside any transaction; a failure is
 * recorded on the export row and never leaves a plaintext file behind.
 */
final class ExportTenantSchema
{
    public function __construct(private readonly StreamCipher $cipher) {}

    public function handle(Tenant $tenant, string $reason = TenantExport::MANUAL, ?int $requestedBy = null): TenantExport
    {
        $export = TenantExport::query()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'status' => TenantExport::PENDING,
            'reason' => $reason,
            'requested_by' => $requestedBy,
        ]);

        $schema = (string) $tenant->database()->getName();
        $workDir = storage_path('app/private/export-work');
        @mkdir($workDir, 0700, true);
        $plain = "{$workDir}/{$export->id}.dump";
        $encrypted = "{$workDir}/{$export->id}.dump.enc";

        try {
            $counts = $this->tableCounts($schema);
            $this->dump($schema, $plain);
            $this->verifyArchive($plain, $schema, array_keys($counts));

            $sha256 = (string) hash_file('sha256', $plain);
            $this->cipher->encrypt($plain, $encrypted);
            $this->verifyDecryptsTo($encrypted, $sha256);

            $disk = (string) config('vendapos.exports.disk');
            $path = sprintf('tenant-exports/%s/%s-%s.dump.enc', $tenant->getTenantKey(), now()->format('Ymd-His'), $export->id);
            $stream = fopen($encrypted, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Cannot read the encrypted export.');
            }

            try {
                Storage::disk($disk)->put($path, $stream);
            } finally {
                fclose($stream);
            }

            $export->forceFill([
                'status' => TenantExport::VERIFIED,
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => Storage::disk($disk)->size($path),
                'sha256' => $sha256,
                'table_counts' => $counts,
                'verified_at' => now(),
                'expires_at' => now()->addYears((int) config('vendapos.exports.retention_years')),
            ])->save();
        } catch (Throwable $e) {
            $export->forceFill(['status' => TenantExport::FAILED, 'error' => Str::limit($e->getMessage(), 1000)])->save();

            throw $e;
        } finally {
            @unlink($plain);
            @unlink($encrypted);
        }

        return $export;
    }

    /**
     * Rows per table at export time (pg_dump takes its own snapshot, so a till
     * syncing meanwhile can make these differ slightly from the dump).
     *
     * @return array<string, int>
     */
    private function tableCounts(string $schema): array
    {
        $central = DB::connection((string) config('tenancy.database.central_connection'));
        $tables = $central->table('information_schema.tables')
            ->where('table_schema', $schema)
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name');

        if ($tables->isEmpty()) {
            throw new RuntimeException("Schema [{$schema}] has no tables.");
        }

        return $tables->mapWithKeys(fn (string $table) => [
            $table => (int) $central->table("{$schema}.{$table}")->count(),
        ])->all();
    }

    private function dump(string $schema, string $file): void
    {
        $db = $this->connection();

        $result = Process::timeout((int) config('vendapos.exports.timeout_seconds'))
            ->env(['PGPASSWORD' => $db['password']]) // never on the command line
            ->run([
                (string) config('vendapos.exports.pg_dump'),
                '--format=custom', '--no-owner', '--no-privileges',
                "--schema={$schema}",
                "--host={$db['host']}", "--port={$db['port']}",
                "--username={$db['username']}", "--dbname={$db['database']}",
                "--file={$file}",
            ]);

        if (! $result->successful()) {
            throw new RuntimeException('pg_dump failed: '.trim($result->errorOutput()));
        }
    }

    /** @param  list<string>  $tables */
    private function verifyArchive(string $file, string $schema, array $tables): void
    {
        $result = Process::timeout(600)->run([(string) config('vendapos.exports.pg_restore'), '--list', $file]);

        if (! $result->successful()) {
            throw new RuntimeException('pg_restore cannot read the dump: '.trim($result->errorOutput()));
        }

        $toc = $result->output();

        foreach ($tables as $table) {
            // TOC lines read "…; 0 16390 TABLE DATA <schema> <table> [owner]".
            if (preg_match('/TABLE DATA '.preg_quote($schema, '/').' '.preg_quote($table, '/').'(\s|$)/m', $toc) !== 1) {
                throw new RuntimeException("The dump has no data entry for table [{$table}].");
            }
        }
    }

    private function verifyDecryptsTo(string $encrypted, string $sha256): void
    {
        $hash = hash_init('sha256');
        $this->cipher->decrypt($encrypted, function (string $chunk) use ($hash): void {
            hash_update($hash, $chunk);
        });

        if (! hash_equals($sha256, hash_final($hash))) {
            throw new RuntimeException('The encrypted export does not decrypt to the dump.');
        }
    }

    /** @return array{host: string, port: string, username: string, password: string, database: string} */
    private function connection(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('database.connections.'.config('tenancy.database.central_connection'));

        return [
            'host' => (string) $config['host'],
            'port' => (string) $config['port'],
            'username' => (string) $config['username'],
            'password' => (string) $config['password'],
            'database' => (string) $config['database'],
        ];
    }
}
