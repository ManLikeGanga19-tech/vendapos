<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * Someone who works for a tenant's business, stored in the tenant's own
 * schema. Owners and managers sign in to the Owner panel with a username on
 * the tenant's domain (guard `tenant`); everyone may have a till PIN.
 *
 * @property string $id
 * @property string $name
 * @property string|null $username
 * @property string|null $password
 * @property bool $is_active
 * @property string|null $locale Owner panel language; null follows the business (Tenant::LOCALES)
 * @property Carbon|null $last_login_at
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property string|null $pin_hash
 * @property Carbon|null $pin_set_at
 * @property Carbon $updated_at
 */
class Staff extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasRoles;
    use HasUuids;

    /** Each person has exactly one of these, most senior first. */
    public const ROLES = ['owner', 'manager', 'cashier', 'waiter'];

    /** Roles a manager may add and look after, in their own branches only. */
    public const TILL_ROLES = ['cashier', 'waiter'];

    /** PIN digits per role: longer where the PIN approves voids and discounts. */
    public const PIN_LENGTHS = ['owner' => 6, 'manager' => 6, 'cashier' => 4, 'waiter' => 4];

    public function roleName(): ?string
    {
        $names = $this->getRoleNames();

        foreach (self::ROLES as $role) {
            if ($names->contains($role)) {
                return $role;
            }
        }

        return null;
    }

    public static function pinLengthFor(string $role): int
    {
        return self::PIN_LENGTHS[$role] ?? 6;
    }

    /** Owners and managers who finished setting up may use the Owner panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'owner'
            && $this->is_active
            && $this->username !== null
            && $this->hasAnyRole(['owner', 'manager']);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return (string) $this->username;
    }

    /** @return array<string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param array<string>|null $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    protected $table = 'staff';

    protected string $guard_name = 'tenant';

    protected $fillable = ['name', 'username', 'password', 'is_active'];

    /** Mirrors the column default so a freshly created model is active too. */
    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'pin_hash'];

    /**
     * The branches whose data this person may see in the Owner panel: every
     * branch for an owner, the assigned ones for a manager, none for anyone
     * else. Always a concrete list, so a missing assignment shows nothing
     * rather than everything. Run inside the tenant's context.
     *
     * @return list<string>
     */
    public function visibleBranchIds(): array
    {
        $ids = match (true) {
            $this->hasRole('owner') => Branch::query()->pluck('id'),
            $this->hasRole('manager') => $this->branches()->pluck('branches.id'),
            default => collect(),
        };

        return array_values($ids->map(fn (mixed $id): string => (string) $id)->all());
    }

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
            'pin_set_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }
}
