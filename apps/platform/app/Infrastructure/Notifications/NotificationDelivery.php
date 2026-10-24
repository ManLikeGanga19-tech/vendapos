<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $notification
 * @property string $channel
 * @property string $recipient
 * @property string $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property Carbon $created_at
 */
final class NotificationDelivery extends Model
{
    use CentralConnection;
    use HasUuids;

    public const string SENT = 'sent';

    public const string REFUSED = 'refused';

    public const UPDATED_AT = null;

    protected $guarded = [];
}
