<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Small key/value notes the sync keeps between runs: cursors, last success,
 * last error.
 *
 * @property string $key
 * @property string|null $value
 */
#[Table(name: 'sync_state', key: 'key', keyType: 'string', incrementing: false)]
#[Unguarded]
final class SyncState extends Model
{
    const CREATED_AT = null;

    public static function read(string $key): ?string
    {
        return self::query()->whereKey($key)->value('value');
    }

    public static function write(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
