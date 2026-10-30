<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentChannel;
use VendaPOS\Core\Enums\PaymentStatus;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A mobile-money payment, keyed by the terminal's UUID: a push to the
 * customer's phone, or a QR code on the bill for the customer to scan.
 * Lives in the tenant's schema. Only a pending payment ever changes.
 *
 * @property string $id
 * @property string|null $order_id
 * @property string $branch_id
 * @property string $device_id
 * @property int|null $payment_account_id the account whose keys pushed it
 * @property MobileMoneyProvider $provider
 * @property PaymentChannel $channel
 * @property string|null $phone null for a QR payment
 * @property string|null $reference a QR payment's account number on the paybill, e.g. "VPK7M2Q9TR"
 * @property string|null $qr_png Safaricom's QR image, base64 PNG
 * @property int $amount_cents
 * @property string $currency
 * @property PaymentStatus $status
 * @property string $callback_token
 * @property string|null $provider_request_id
 * @property string|null $provider_reference
 * @property string|null $failure_code
 * @property string|null $failure_reason
 * @property Carbon|null $initiated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $query_backoff_until no status query before this (the provider said 429)
 * @property Carbon $created_at
 */
class Payment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['callback_token', 'qr_png'];

    protected function casts(): array
    {
        return [
            'provider' => MobileMoneyProvider::class,
            'channel' => PaymentChannel::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'payment_account_id' => 'integer',
            'callback_token' => 'encrypted',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'query_backoff_until' => 'datetime',
        ];
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }

    /** Where the provider reports the result. The token is the only credential a callback carries. */
    public function callbackUrl(): string
    {
        return rtrim((string) config('vendapos.payments.callback_base_url'), '/')
            ."/webhooks/payments/{$this->callback_token}";
    }
}
