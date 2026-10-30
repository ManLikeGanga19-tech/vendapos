<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * The single row saying which till this is. Written only by App\Till\Till.
 *
 * @property int $id
 * @property string $device_id
 * @property string $device_name
 * @property string|null $till_code "T2", from the server; null until it has said
 * @property string $business_name
 * @property string $branch_id
 * @property string $branch_name
 * @property string $business_type
 * @property string $terminal_mode
 * @property string $token_ciphertext
 * @property CarbonImmutable|null $token_expires_at
 * @property string $status
 * @property CarbonImmutable $paired_at
 * @property CarbonImmutable|null $removed_at
 */
#[Table(name: 'till_identity', incrementing: false, timestamps: false)]
#[Unguarded]
#[Hidden(['token_ciphertext'])]
final class TillIdentity extends Model
{
    public const int ROW_ID = 1;

    public const string ACTIVE = 'active';

    /** The server refused the till's token: it was revoked or expired past the grace window. */
    public const string REMOVED = 'removed';

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'immutable_datetime',
            'paired_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }
}
