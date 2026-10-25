<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * An encrypted pg_dump of one tenant's schema. "verified" means pg_dump
 * succeeded, pg_restore can read the archive, every table of the schema is
 * in it, and the encrypted file decrypts back to the same SHA-256.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $status pending|verified|failed
 * @property string|null $reason why it was taken: manual|purge
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $size_bytes encrypted size
 * @property string|null $sha256 of the plaintext dump
 * @property array<string, int>|null $table_counts rows per table when exported
 * @property string|null $error
 * @property int|null $requested_by
 * @property Carbon|null $verified_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TenantExport extends Model
{
    use CentralConnection;
    use HasUuids;

    public const string PENDING = 'pending';

    public const string VERIFIED = 'verified';

    public const string FAILED = 'failed';

    public const string FOR_PURGE = 'purge';

    public const string MANUAL = 'manual';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'table_counts' => 'array',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }
}
