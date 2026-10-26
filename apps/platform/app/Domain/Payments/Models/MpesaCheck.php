<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Something about M-Pesa money the owner should look at. Opened by the
 * matching, closed only by a person.
 *
 * @property int $id
 * @property string $kind
 * @property string $dedupe_key
 * @property string|null $branch_id
 * @property string|null $order_id
 * @property string|null $mpesa_code
 * @property string|null $payment_id
 * @property int|null $amount_cents
 * @property array<string, mixed>|null $details
 * @property string $status
 * @property string|null $resolved_by
 * @property string|null $resolved_note
 * @property Carbon|null $resolved_at
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 */
class MpesaCheck extends Model
{
    /** A typed code Safaricom never confirmed within the grace period. */
    public const string UNCONFIRMED_CODE = 'unconfirmed_code';

    /** A typed code confirmed for a different amount than the sale took. */
    public const string AMOUNT_MISMATCH = 'amount_mismatch';

    /** The same code typed for more than one sale. */
    public const string CODE_REUSED = 'code_reused';

    /** A push the customer paid that no synced sale claims (e.g. the cashier stopped waiting). */
    public const string UNCLAIMED_PUSH = 'unclaimed_push';

    public const array KINDS = [self::UNCONFIRMED_CODE, self::AMOUNT_MISMATCH, self::CODE_REUSED, self::UNCLAIMED_PUSH];

    public const string OPEN = 'open';

    public const string RESOLVED = 'resolved';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
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
