<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Enums\CashMovementKind;

/** @return array<string, mixed> */
function movementPayload(array $overrides = []): array
{
    return array_merge([
        'uuid' => fake()->uuid(),
        'kind' => 'pay_out',
        'amount_cents' => 50_000,
        'moved_at' => '2026-11-21T14:10:00+03:00',
        'staff_id' => fake()->uuid(),
        'approved_by' => fake()->uuid(),
        'reason' => 'Charcoal',
        'order_uuid' => null,
    ], $overrides);
}

/** @return list<string> */
function movementErrors(array $overrides): array
{
    return Validator::make(movementPayload($overrides), CashMovementData::rules())->errors()->keys();
}

it('creates a pay-out from a valid payload', function () {
    $movement = CashMovementData::from(movementPayload());

    expect($movement->kind)->toBe(CashMovementKind::PayOut)
        ->and($movement->amount()->cents)->toBe(50_000)
        ->and(movementErrors([]))->toBe([]);
});

it('needs a manager for a pay-out or a drop, and a reason for a pay-out', function () {
    expect(movementErrors(['approved_by' => null]))->toContain('approved_by')
        ->and(movementErrors(['reason' => null]))->toContain('reason')
        ->and(movementErrors(['kind' => 'drop', 'reason' => null]))->toBe([])
        ->and(movementErrors(['kind' => 'drop', 'approved_by' => null]))->toContain('approved_by');
});

it('ties a void refund to its sale, and only a void refund', function () {
    $refund = ['kind' => 'void_refund', 'approved_by' => null, 'reason' => null];

    expect(movementErrors($refund))->toContain('order_uuid')
        ->and(movementErrors([...$refund, 'order_uuid' => fake()->uuid()]))->toBe([])
        ->and(movementErrors(['order_uuid' => fake()->uuid()]))->toContain('order_uuid');
});

it('refuses no cash, an unknown kind and a long reason', function (array $overrides, string $field) {
    expect(movementErrors($overrides))->toContain($field);
})->with([
    [['amount_cents' => 0], 'amount_cents'],
    [['kind' => 'pay_in'], 'kind'],
    [['reason' => str_repeat('x', CashMovementData::REASON_MAX + 1)], 'reason'],
]);
