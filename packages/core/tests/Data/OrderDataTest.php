<?php

declare(strict_types=1);

use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\ValueObjects\Money;

it('creates from valid payload', function () {
    $order = OrderData::from([
        'uuid' => fake()->uuid(),
        'tenant_id' => 'tenant-abc',
        'branch_id' => fake()->uuid(),
        'device_id' => fake()->uuid(),
        'lines' => [
            [
                'item_id' => fake()->uuid(),
                'name' => 'Nyama Choma',
                'quantity' => 2,
                'unit_price_cents' => 80000,
                'vat_category' => 'standard',
                'line_total_cents' => 160000,
            ],
        ],
        'payment_type' => 'mpesa',
        'subtotal_cents' => 137931,
        'vat_cents' => 22069,
        'total_cents' => 160000,
    ]);

    expect($order->uuid)->toBeString();
    expect($order->payment_type)->toBe(PaymentType::Mpesa);
    expect($order->total())->toBeInstanceOf(Money::class);
    expect($order->total()->cents)->toBe(160000);
    expect($order->lines)->toHaveCount(1);
});

it('exposes money accessors', function () {
    $order = OrderData::from([
        'uuid' => fake()->uuid(),
        'tenant_id' => 'tenant-abc',
        'branch_id' => fake()->uuid(),
        'device_id' => fake()->uuid(),
        'lines' => [
            [
                'item_id' => fake()->uuid(),
                'name' => 'Tusker',
                'quantity' => 1,
                'unit_price_cents' => 25000,
                'vat_category' => 'standard',
                'line_total_cents' => 25000,
            ],
        ],
        'payment_type' => 'cash',
        'subtotal_cents' => 21552,
        'vat_cents' => 3448,
        'total_cents' => 25000,
    ]);

    expect($order->subtotal()->cents)->toBe(21552);
    expect($order->vat()->cents)->toBe(3448);
    expect($order->total()->cents)->toBe(25000);
});
