<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\ValueObjects\Money;

/**
 * A sale as the terminal sends it. Tenant, branch and device are not part of
 * the payload: the server takes them from the authenticated device, never
 * from the body (CLAUDE.md §3.1).
 */
final class OrderData extends Data
{
    /**
     * @param  DataCollection<int, OrderLineData>  $lines
     */
    public function __construct(
        // Client-generated; the idempotency key for sync.
        public string $uuid,
        // Per-device, strictly increasing; orders offline sales and later feeds eTIMS.
        public int $sequence_number,
        // When the sale happened on the terminal, ISO 8601.
        public string $placed_at,
        public OrderStatus $status,
        #[DataCollectionOf(OrderLineData::class)]
        public DataCollection $lines,
        public PaymentType $payment_type,
        public int $subtotal_cents,
        public int $vat_cents,
        public int $total_cents,
    ) {}

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents);
    }

    public function vat(): Money
    {
        return Money::fromCents($this->vat_cents);
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'sequence_number' => ['required', 'integer', 'min:1'],
            'placed_at' => ['required', 'date'],
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
            'subtotal_cents' => ['required', 'integer', 'min:0'],
            'vat_cents' => ['required', 'integer', 'min:0'],
            'total_cents' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Order and line rules together, for validating one raw order payload.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        $rules = self::rules();

        foreach (OrderLineData::rules() as $field => $lineRules) {
            $rules["lines.*.{$field}"] = $lineRules;
        }

        return $rules;
    }
}
