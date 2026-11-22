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
        'staff_id' => fake()->uuid(),
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
        'tenders' => [
            ['method' => 'mpesa_push', 'amount_cents' => 160_000, 'payment_uuid' => fake()->uuid()],
        ],
        'subtotal_cents' => 137_931,
        'vat_cents' => 22_069,
        'total_cents' => 160_000,
    ], $overrides);
}

it('creates from a valid payload', function () {
    $order = OrderData::from(orderPayload());

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->paymentType())->toBe(PaymentType::Mpesa)
        ->and($order->tenders)->toHaveCount(1)
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

it('names who made the sale', function () {
    $staffId = fake()->uuid();

    expect(OrderData::from(orderPayload(['staff_id' => $staffId]))->staff_id)->toBe($staffId)
        ->and(Validator::make(orderPayload(['staff_id' => null]), OrderData::validationRules())->errors()->keys())->toContain('staff_id')
        ->and(Validator::make(orderPayload(['staff_id' => 'cashier-1']), OrderData::validationRules())->errors()->keys())->toContain('staff_id');
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

it('validates tenders with the order', function () {
    $invalid = Validator::make(orderPayload([
        'tenders' => [['method' => 'card', 'amount_cents' => 0, 'payment_uuid' => 'nope', 'mpesa_reference' => 'sj12abc3de']],
    ]), OrderData::validationRules());

    expect($invalid->errors()->keys())->toContain('tenders.0.method', 'tenders.0.amount_cents', 'tenders.0.payment_uuid', 'tenders.0.mpesa_reference');
});

it('requires the tenders key, even when empty, and caps how many', function () {
    $missing = orderPayload();
    unset($missing['tenders']);
    $tooMany = orderPayload(['tenders' => array_fill(0, 11, ['method' => 'cash', 'amount_cents' => 100, 'tendered_cents' => 100, 'change_cents' => 0])]);

    expect(Validator::make($missing, OrderData::validationRules())->errors()->keys())->toContain('tenders')
        ->and(Validator::make(orderPayload(['status' => 'cancelled', 'tenders' => []]), OrderData::validationRules())->passes())->toBeTrue()
        ->and(Validator::make($tooMany, OrderData::validationRules())->errors()->keys())->toContain('tenders');
});

it('names the drawer shift it was sold in, and still reads a sale from a till older than cash-up', function () {
    $shift = fake()->uuid();

    expect(OrderData::from(orderPayload(['shift_id' => $shift]))->shift_id)->toBe($shift)
        ->and(OrderData::from(orderPayload())->shift_id)->toBeNull()
        ->and(Validator::make(orderPayload(), OrderData::validationRules())->passes())->toBeTrue()
        ->and(Validator::make(orderPayload(['shift_id' => 'not-a-uuid']), OrderData::validationRules())->errors()->keys())->toContain('shift_id');
});
