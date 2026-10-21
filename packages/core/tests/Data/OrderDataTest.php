<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\ValueObjects\Money;

/** @return array<string, mixed> */
function orderPayload(array $overrides = []): array
{
    return array_merge([
        'uuid' => fake()->uuid(),
        'sequence_number' => 42,
        'placed_at' => '2026-10-21T18:30:00+03:00',
        'status' => 'completed',
        'lines' => [
            [
                'item_id' => fake()->uuid(),
                'name' => 'Nyama Choma',
                'quantity' => 2,
                'unit_price_cents' => 80_000,
                'vat_category' => 'standard',
                'line_total_cents' => 160_000,
            ],
        ],
        'payment_type' => 'mpesa',
        'subtotal_cents' => 137_931,
        'vat_cents' => 22_069,
        'total_cents' => 160_000,
    ], $overrides);
}

it('creates from a valid payload', function () {
    $order = OrderData::from(orderPayload());

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->payment_type)->toBe(PaymentType::Mpesa)
        ->and($order->sequence_number)->toBe(42)
        ->and($order->total())->toBeInstanceOf(Money::class)
        ->and($order->total()->cents)->toBe(160_000)
        ->and($order->lines)->toHaveCount(1);
});

it('exposes money accessors', function () {
    $order = OrderData::from(orderPayload());

    expect($order->subtotal()->cents)->toBe(137_931)
        ->and($order->vat()->cents)->toBe(22_069);
});

it('carries no tenant, branch or device: the server takes those from the device token', function () {
    $properties = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(OrderData::class))->getProperties());

    expect($properties)->not->toContain('tenant_id')
        ->not->toContain('branch_id')
        ->not->toContain('device_id');
});

it('validates orders and their lines together', function () {
    $valid = Validator::make(orderPayload(), OrderData::validationRules());
    $invalid = Validator::make(orderPayload([
        'status' => 'refunded',
        'lines' => [['item_id' => 'nope', 'quantity' => 0, 'vat_category' => 'luxury']],
    ]), OrderData::validationRules());

    expect($valid->passes())->toBeTrue()
        ->and($invalid->errors()->keys())->toContain('status', 'lines.0.item_id', 'lines.0.quantity', 'lines.0.vat_category', 'lines.0.name');
});
