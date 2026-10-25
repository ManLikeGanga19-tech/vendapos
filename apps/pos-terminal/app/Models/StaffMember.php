<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone who can sign in on this till, copied from the server. PINs are
 * checked against the hash locally so sign-in works offline; the hash itself
 * is sealed by the OS keyring (SecretBox) before it is stored.
 *
 * @property string $id
 * @property string $name
 * @property string $role
 * @property list<string> $permissions
 * @property string $pin_hash_sealed
 * @property int $pin_length
 */
#[Table(name: 'staff', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
#[Hidden(['pin_hash_sealed'])]
final class StaffMember extends Model
{
    /** Permission names as the server sends them (tenant migration ROLE_PERMISSIONS). */
    public const string TAKE_ORDERS = 'orders.take';

    public const string TAKE_PAYMENTS = 'payments.take';

    public const string REPRINT_RECEIPTS = 'receipts.reprint';

    public const string VOID_ORDERS = 'orders.void';

    public const string DISCOUNT_ORDERS = 'orders.discount';

    public const string OPEN_DRAWER = 'drawer.open';

    public const string CLOSE_SHIFTS = 'shifts.close';

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'pin_length' => 'integer',
        ];
    }
}
