<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use VendaPOS\Core\Enums\BusinessType;

/**
 * @property string|null $name
 * @property string|null $owner_name
 * @property string|null $owner_phone E.164
 * @property string|null $owner_email
 * @property string $locale en|sw, the language the owner is written to in
 * @property list<string>|null $modules BusinessType values: the business modules this tenant has (T7)
 * @property int $cash_variance_allowed_cents how far a drawer count may be off before closing needs a manager
 * @property string $status active|suspended|purged
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property Carbon|null $purge_after when a suspended tenant's data is exported and deleted
 * @property int|null $suspended_by
 */
class Tenant extends BaseTenant implements HasLocalePreference, TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use Notifiable;

    public const array LOCALES = ['en', 'sw'];

    public const string ACTIVE = 'active';

    /** Panels and new payments are blocked; tills keep syncing and callbacks keep landing. */
    public const string SUSPENDED = 'suspended';

    /** Data exported and deleted; only this row and the audit trail remain. */
    public const string PURGED = 'purged';

    /** The column defaults, so a tenant created in this request already has them. */
    protected $attributes = [
        'status' => self::ACTIVE,
        'locale' => 'en',
        'cash_variance_allowed_cents' => self::DEFAULT_CASH_VARIANCE_ALLOWED_CENTS,
    ];

    /** Cash-up: KES 100 either way before closing a shift needs a manager (Daniel). */
    public const int DEFAULT_CASH_VARIANCE_ALLOWED_CENTS = 10_000;

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'purge_after' => 'datetime',
            'modules' => 'array',
            'cash_variance_allowed_cents' => 'integer',
        ];
    }

    /**
     * The business modules this tenant has; each branch runs one of them.
     *
     * @return list<BusinessType>
     */
    public function moduleTypes(): array
    {
        // A module name this version does not know is skipped, not an error.
        return array_values(array_filter(array_map(
            fn (string $value) => BusinessType::tryFrom($value),
            $this->modules ?? [],
        )));
    }

    public function hasModule(BusinessType $type): bool
    {
        return in_array($type, $this->moduleTypes(), true);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::SUSPENDED;
    }

    /**
     * Attributes stored in real columns; everything else goes to the `data` JSON.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'owner_name',
            'owner_phone',
            'owner_email',
            'locale',
            'modules',
            'cash_variance_allowed_cents',
            'status',
            'suspended_at',
            'suspension_reason',
            'purge_after',
            'suspended_by',
            'created_at',
            'updated_at',
        ];
    }

    public function preferredLocale(): string
    {
        return in_array($this->locale, self::LOCALES, true) ? $this->locale : 'en';
    }

    /** Owner notifications go to the owner, not to the business. */
    public function routeNotificationForMail(): ?string
    {
        return $this->owner_email;
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->owner_phone;
    }
}
