<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An M-Pesa code a till typed for a sale, waiting for (or matched to)
 * Safaricom's confirmation of the money.
 *
 * @property int $id
 * @property string $order_id
 * @property string $branch_id
 * @property string $mpesa_code
 * @property int $amount_cents
 * @property string $status
 * @property int|null $mpesa_confirmation_id
 * @property Carbon $created_at
 * @property Carbon|null $checked_at
 */
class MpesaCodeClaim extends Model
{
    public const string PENDING = 'pending';

    public const string CONFIRMED = 'confirmed';

    public const string AMOUNT_MISMATCH = 'amount_mismatch';

    public const string UNCONFIRMED = 'unconfirmed';

    public const string REUSED = 'reused';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'checked_at' => 'datetime',
        ];
    }
}
