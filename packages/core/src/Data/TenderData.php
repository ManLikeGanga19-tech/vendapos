<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\ValueObjects\Money;

/**
 * One way part of a sale was paid, in KES cents. Only cash has money handed
 * over and change; only an M-Pesa push names the till's payment; only a
 * typed M-Pesa code carries that code. Tenders::problems() checks how they
 * fit together and against the order.
 */
final class TenderData extends Data
{
    public function __construct(
        public TenderMethod $method,
        // What this tender pays towards the sale.
        public int $amount_cents,
        // Cash only: the notes and coins handed over, and the change given back.
        public ?int $tendered_cents = null,
        public ?int $change_cents = null,
        // M-Pesa push only: the UUID of the payment the till started.
        public ?string $payment_uuid = null,
        // Typed M-Pesa code only, as the customer's SMS shows it.
        public ?string $mpesa_reference = null,
    ) {}

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(TenderMethod::class)],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'tendered_cents' => ['nullable', 'integer', 'min:1'],
            'change_cents' => ['nullable', 'integer', 'min:0'],
            'payment_uuid' => ['nullable', 'string', 'uuid'],
            // M-Pesa transaction codes are ten capital letters and digits.
            'mpesa_reference' => ['nullable', 'string', 'regex:/^[A-Z0-9]{10}$/'],
        ];
    }
}
