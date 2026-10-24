<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One suspension, reactivation or purge of a tenant: the central audit trail,
 * kept after the tenant's own data is gone.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $action
 * @property string|null $reason
 * @property int|null $actor_id null when the system acted (scheduled purge)
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
class TenantStatusChange extends Model
{
    use CentralConnection;
    use HasUuids;

    public const string SUSPENDED = 'suspended';

    public const string REACTIVATED = 'reactivated';

    public const string PURGED = 'purged';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
