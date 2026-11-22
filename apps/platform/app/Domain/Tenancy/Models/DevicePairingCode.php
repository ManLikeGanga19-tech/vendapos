<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A one-time code for pairing a new till with a branch (central table).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $device_name
 * @property string $code_hash SHA-256 of the normalised code; the code itself is never stored
 * @property string $issued_by staff ID
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $device_id
 * @property Carbon $created_at
 */
class DevicePairingCode extends Model
{
    use CentralConnection;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'branch_id', 'device_name', 'code_hash', 'issued_by', 'expires_at', 'used_at', 'device_id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
