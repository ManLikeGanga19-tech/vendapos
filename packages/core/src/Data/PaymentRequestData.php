<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Rules\PhoneNumber;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A terminal asking for a mobile-money push to the customer's phone. Like
 * orders, it carries no tenant, branch or device: those come from the token.
 */
final class PaymentRequestData extends Data
{
    public function __construct(
        // Client-generated; the idempotency key for the payment.
        public string $uuid,
        // The sale this pays for. No foreign key: a payment usually exists before its order syncs.
        public ?string $order_uuid,
        public MobileMoneyProvider $provider,
        // E.164, normalised by the API before validation.
        public string $phone,
        public int $amount_cents,
    ) {}

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'order_uuid' => ['nullable', 'string', 'uuid'],
            'provider' => ['required', Rule::enum(MobileMoneyProvider::class)],
            'phone' => ['required', 'string', new PhoneNumber],
            // Whole shillings only: mobile-money APIs take no cents.
            'amount_cents' => ['required', 'integer', 'min:100', 'multiple_of:100'],
        ];
    }
}
