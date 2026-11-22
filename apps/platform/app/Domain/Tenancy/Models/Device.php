<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property int $till_number
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_seen_at
 */
class Device extends Model
{
    use CentralConnection;
    use HasApiTokens;
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'name'];

    protected static function booted(): void
    {
        // The next number in the business; EnrolDevice locks the tenant so two pairings can't share one.
        static::creating(function (Device $device) {
            $device->till_number ??= (int) static::query()->where('tenant_id', $device->tenant_id)->max('till_number') + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'till_number' => 'integer',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * One tenant's devices at the given branches. Devices live in a central
     * table, so the tenant is always part of the filter, never just the branch.
     *
     * @param  Builder<Device>  $query
     * @param  list<string>  $branchIds
     */
    public function scopeOfTenantBranches(Builder $query, string $tenantId, array $branchIds): void
    {
        $query->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds);
    }

    /** "T2": the till's short name on receipts. */
    public function tillCode(): string
    {
        return "T{$this->till_number}";
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
