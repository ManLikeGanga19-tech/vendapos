<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money paid straight into one of the business's accounts, as Safaricom
 * confirmed it (C2B). Typed M-Pesa codes at the till are checked against these.
 *
 * @property int $id
 * @property int $payment_account_id
 * @property string $trans_id
 * @property string|null $trans_type
 * @property int $amount_cents
 * @property string $business_short_code
 * @property string|null $bill_ref
 * @property string|null $msisdn masked by Safaricom
 * @property string|null $payer_name
 * @property Carbon $paid_at
 * @property int|null $webhook_receipt_id
 * @property Carbon $created_at
 */
class MpesaConfirmation extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
