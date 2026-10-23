<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time link for a staff member to choose their username and password.
 *
 * @property string $id
 * @property string $staff_id
 * @property string $token_hash SHA-256 of the token; the token itself is never stored
 * @property string $issued_by
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon $created_at
 */
class StaffSetupLink extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['staff_id', 'token_hash', 'issued_by', 'expires_at', 'used_at'];

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
