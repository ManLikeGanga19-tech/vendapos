<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A POS terminal (or other device) enrolled to one tenant's branch.
 * API requests authenticate as a device, and the tenant is taken from it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $name
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_seen_at
 */
class Device extends Model
{
    use CentralConnection;
    use HasApiTokens;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'name'];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
        $this->tokens()->delete();
    }
}
