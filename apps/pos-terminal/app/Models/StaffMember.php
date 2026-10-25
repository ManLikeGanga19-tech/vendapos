<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone who can sign in on this till, copied from the server. PINs are
 * checked against the hash locally so sign-in works offline.
 *
 * @property string $id
 * @property string $name
 * @property string $role
 * @property list<string> $permissions
 * @property string $pin_hash
 * @property int $pin_length
 */
#[Table(name: 'staff', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
#[Hidden(['pin_hash'])]
final class StaffMember extends Model
{
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'pin_length' => 'integer',
        ];
    }
}
