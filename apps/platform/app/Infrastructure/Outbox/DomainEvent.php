<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One row of the append-only outbox (architecture §6.3). Written on the
 * current connection so it commits with the change it describes; inside a
 * tenant that is the tenant connection, reaching the central table by its
 * schema-qualified name.
 *
 * @property string $event_id
 * @property string $event_type
 * @property string $tenant_id
 * @property string|null $branch_id
 * @property string|null $device_id
 * @property string|null $aggregate_id
 * @property Carbon $occurred_at
 * @property int $schema_ver
 * @property array<string, mixed> $payload
 */
final class DomainEvent extends Model
{
    use HasUuids;

    public const int SCHEMA_VERSION = 1;

    protected $table = 'public.domain_events';

    protected $primaryKey = 'event_id';

    public $timestamps = false;

    protected $fillable = ['event_type', 'tenant_id', 'branch_id', 'device_id', 'aggregate_id', 'occurred_at', 'schema_ver', 'payload'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function record(
        string $type,
        string $tenantId,
        array $payload,
        ?string $aggregateId = null,
        ?string $branchId = null,
        ?string $deviceId = null,
    ): self {
        return self::create([
            'event_type' => $type,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'device_id' => $deviceId,
            'aggregate_id' => $aggregateId,
            'occurred_at' => now(),
            'schema_ver' => self::SCHEMA_VERSION,
            'payload' => $payload,
        ]);
    }

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('domain_events is append-only.');

        self::updating($refuse);
        self::deleting($refuse);
    }
}
