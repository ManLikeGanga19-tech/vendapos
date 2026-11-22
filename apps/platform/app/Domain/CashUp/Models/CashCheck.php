<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A cash-up problem the owner should look at. The server never refuses a
 * shift the till already closed, since the cash is counted and gone by
 * then; it opens one of these instead.
 *
 * @property int $id
 * @property string $kind
 * @property string $dedupe_key
 * @property string|null $branch_id
 * @property string|null $shift_id
 * @property string|null $order_id
 * @property string|null $staff_id
 * @property string|null $approved_by
 * @property int|null $amount_cents
 * @property Carbon|null $business_date
 * @property array<string, mixed>|null $details
 * @property string $status
 * @property string|null $resolved_by null on a resolved check: the server settled it
 * @property string|null $resolved_note
 * @property Carbon|null $resolved_at
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 */
class CashCheck extends Model
{
    /** The drawer's count is off the server's expected cash by more than the business allows. */
    public const string VARIANCE_OVER_LIMIT = 'variance_over_limit';

    /** The till expected different cash than the server worked out: a sale or movement is missing on one side. */
    public const string FIGURES_DIFFER = 'figures_differ';

    /** A close, pay-out or drop approved by someone who may not close shifts at that branch. */
    public const string APPROVER_NOT_ALLOWED = 'approver_not_allowed';

    /** A shift still open long after it was opened: a till left running, lost or broken. */
    public const string OPEN_TOO_LONG = 'open_too_long';

    /** A sale from a till that runs cash-up, made outside any shift. */
    public const string SALE_OUTSIDE_SHIFT = 'sale_outside_shift';

    public const array KINDS = [self::VARIANCE_OVER_LIMIT, self::FIGURES_DIFFER, self::APPROVER_NOT_ALLOWED, self::OPEN_TOO_LONG, self::SALE_OUTSIDE_SHIFT];

    /** The kinds a late sale can put right, so the server settles them itself. */
    public const array SETTLE_ON_THEIR_OWN = [self::VARIANCE_OVER_LIMIT, self::FIGURES_DIFFER];

    public const string OPEN = 'open';

    public const string RESOLVED = 'resolved';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'business_date' => 'date',
            'details' => 'array',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Opens a check once; finding the same problem again changes nothing. A check the
     * server settled reopens, with the new figures, if the problem comes back.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function open(string $kind, string $dedupeKey, array $attributes): void
    {
        $key = "{$kind}:{$dedupeKey}";
        $details = isset($attributes['details']) ? json_encode($attributes['details']) : null;

        $inserted = self::query()->insertOrIgnore([[
            ...$attributes,
            'details' => $details,
            'kind' => $kind,
            'dedupe_key' => $key,
            'status' => self::OPEN,
            'created_at' => now(),
        ]]);

        if ($inserted === 0) {
            self::query()->where('dedupe_key', $key)->where('status', self::RESOLVED)->whereNull('resolved_by')->update([
                ...$attributes,
                'details' => $details,
                'status' => self::OPEN,
                'resolved_note' => null,
                'resolved_at' => null,
                'notified_at' => null,
            ]);
        }
    }

    /** The server settles an open check whose problem went away (a late sale arrived). A person's resolution stays. */
    public static function settle(string $kind, string $dedupeKey): void
    {
        self::query()->where('dedupe_key', "{$kind}:{$dedupeKey}")->where('status', self::OPEN)->update([
            'status' => self::RESOLVED,
            'resolved_by' => null,
            'resolved_at' => now(),
        ]);
    }
}
