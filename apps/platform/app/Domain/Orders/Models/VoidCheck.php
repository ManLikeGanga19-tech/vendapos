<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A void the owner should look at (T5a-2). The server never refuses a void
 * the till already made, since the money or the kitchen order is gone by
 * then; it opens one of these instead. Closed only by a person.
 *
 * @property int $id
 * @property string $kind
 * @property string $dedupe_key
 * @property string|null $branch_id
 * @property string|null $order_id
 * @property string|null $ticket_id
 * @property string|null $staff_id
 * @property string|null $approved_by
 * @property Carbon|null $business_date
 * @property array<string, mixed>|null $details
 * @property string $status
 * @property string|null $resolved_by
 * @property string|null $resolved_note
 * @property Carbon|null $resolved_at
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 */
class VoidCheck extends Model
{
    /** A sale void or a ticket item taken off, approved by someone who may not approve voids at that branch. */
    public const string APPROVER_NOT_ALLOWED = 'approver_not_allowed';

    /** A sale voided on a later business day than it was made: a refund, not a void. */
    public const string LATE_VOID = 'late_void';

    /** A staff member whose voided sales were over the limit share of their sales that day. */
    public const string HIGH_VOID_RATE = 'high_void_rate';

    public const array KINDS = [self::APPROVER_NOT_ALLOWED, self::LATE_VOID, self::HIGH_VOID_RATE];

    public const string OPEN = 'open';

    public const string RESOLVED = 'resolved';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'details' => 'array',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Opens a check once; finding the same problem again changes nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function open(string $kind, string $dedupeKey, array $attributes): void
    {
        self::query()->insertOrIgnore([[
            ...$attributes,
            'details' => isset($attributes['details']) ? json_encode($attributes['details']) : null,
            'kind' => $kind,
            'dedupe_key' => "{$kind}:{$dedupeKey}",
            'status' => self::OPEN,
            'created_at' => now(),
        ]]);
    }
}
