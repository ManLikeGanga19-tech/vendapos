<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string|null $name
 * @property string|null $owner_name
 * @property string|null $owner_phone E.164
 * @property string|null $owner_email
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

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
            'created_at',
            'updated_at',
        ];
    }
}
