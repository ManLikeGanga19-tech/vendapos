<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Someone who works for a tenant's business, stored in the tenant's own
 * schema. Signs in with a username on the tenant's domain (guard `tenant`).
 *
 * @property string $id
 * @property string $name
 * @property string|null $username
 * @property string|null $password
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
class Staff extends Authenticatable
{
    use HasRoles;
    use HasUuids;

    protected $table = 'staff';

    protected string $guard_name = 'tenant';

    protected $fillable = ['name', 'username', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /** @return HasMany<StaffSetupLink, $this> */
    public function setupLinks(): HasMany
    {
        return $this->hasMany(StaffSetupLink::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }
}
