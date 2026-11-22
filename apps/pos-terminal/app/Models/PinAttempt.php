<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Wrong PINs in a row for one person on this till.
 *
 * @property string $staff_id
 * @property int $failures
 * @property CarbonImmutable|null $locked_until
 */
#[Table(name: 'pin_attempts', key: 'staff_id', keyType: 'string', incrementing: false)]
#[Unguarded]
final class PinAttempt extends Model
{
    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'failures' => 'integer',
            'locked_until' => 'immutable_datetime',
        ];
    }
}
