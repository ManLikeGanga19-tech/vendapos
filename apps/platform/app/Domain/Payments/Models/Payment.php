<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentStatus;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A mobile-money push to a customer's phone, keyed by the terminal's UUID.
 * Lives in the tenant's schema. Only a pending payment ever changes.
 *
 * @property string $id
 * @property string|null $order_id
 * @property string $branch_id
 * @property string $device_id
 * @property MobileMoneyProvider $provider
 * @property string $phone
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
 * @property Carbon $created_at
 */
class Payment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['callback_token'];

    protected function casts(): array
    {
        return [
            'provider' => MobileMoneyProvider::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'callback_token' => 'encrypted',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
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
