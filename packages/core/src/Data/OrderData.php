<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\ValueObjects\Money;

final class OrderData extends Data
{
    /**
     * @param  DataCollection<int, OrderLineData>  $lines
     */
    public function __construct(
        public string $uuid,
        public string $tenant_id,
        public string $branch_id,
        public string $device_id,
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
            'tenant_id' => ['required', 'string'],
            'branch_id' => ['required', 'string', 'uuid'],
            'device_id' => ['required', 'string', 'uuid'],
            'lines' => ['required', 'array', 'min:1'],
            'payment_type' => ['required', 'string'],
            'subtotal_cents' => ['required', 'integer', 'min:0'],
            'vat_cents' => ['required', 'integer', 'min:0'],
            'total_cents' => ['required', 'integer', 'min:0'],
        ];
    }
}
