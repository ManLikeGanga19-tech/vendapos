<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notifiable;
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
 */
class Tenant extends BaseTenant implements HasLocalePreference, TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use Notifiable;

    public const array LOCALES = ['en', 'sw'];

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
