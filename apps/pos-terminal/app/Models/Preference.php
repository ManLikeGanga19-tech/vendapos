<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Settings of this machine that survive pairing it again, unlike SyncState.
 *
 * @property string $key
 * @property string|null $value
 */
#[Table(name: 'preferences', key: 'key', keyType: 'string', incrementing: false)]
#[Unguarded]
final class Preference extends Model
{
    const CREATED_AT = null;

    public const string LOCALE = 'locale';

    public static function read(string $key): ?string
    {
        return self::query()->whereKey($key)->value('value');
    }

    public static function write(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
