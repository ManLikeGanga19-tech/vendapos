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

/**
 * @property string|null $name
 * @property string|null $owner_name
 * @property string|null $owner_phone E.164
 * @property string|null $owner_email
 * @property string $locale en|sw, the language the owner is written to in
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
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
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
