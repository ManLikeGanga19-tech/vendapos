<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\TicketData;
use VendaPOS\Core\Data\TicketLineVoidData;
use VendaPOS\Core\Enums\VoidReason;

/** @return array<string, mixed> */
function lineVoidPayload(array $overrides = []): array
{
    return array_merge([
        'uuid' => fake()->uuid(),
        'line_uuid' => fake()->uuid(),
        'quantity' => 1,
        'voided_at' => '2026-10-27T13:20:00+03:00',
        'voided_by' => fake()->uuid(),
        'approved_by' => fake()->uuid(),
        'reason' => 'changed_mind',
        'note' => null,
    ], $overrides);
}

it('creates from a valid payload', function () {
    $void = TicketLineVoidData::from(lineVoidPayload(['quantity' => 2]));

    expect($void->quantity)->toBe(2)
        ->and($void->reason)->toBe(VoidReason::ChangedMind);
});

it('offers every sale reason but "payment problem" for ticket lines', function () {
    expect(VoidReason::forTicketLines())->toBe([VoidReason::WrongItem, VoidReason::ChangedMind, VoidReason::Duplicate, VoidReason::Other])
        ->and(Validator::make(lineVoidPayload(['reason' => 'payment_problem']), TicketLineVoidData::rules())->errors()->keys())->toBe(['reason']);
});

it('needs a whole number of at least one, and a note for "other"', function (array $overrides, string $field) {
    expect(Validator::make(lineVoidPayload($overrides), TicketLineVoidData::rules())->errors()->keys())->toContain($field);
})->with([
    'none' => [['quantity' => 0], 'quantity'],
    'a fraction' => [['quantity' => 1.5], 'quantity'],
    'other, unexplained' => [['reason' => 'other', 'note' => null], 'note'],
    'a long note' => [['reason' => 'other', 'note' => str_repeat('a', 201)], 'note'],
    'no approver' => [['approved_by' => null], 'approved_by'],
]);

it('carries voids on a ticket, and none when the till sends none', function () {
    $void = lineVoidPayload();
    $base = ticketPayload();

    expect(TicketData::from([...$base, 'voids' => [$void]])->voidList()[0]->uuid)->toBe($void['uuid'])
        ->and(TicketData::from($base)->voidList())->toBe([]);
});

it('checks each void of a ticket on its own, the note against its own reason', function () {
    $payload = ticketPayload(['voids' => [
        lineVoidPayload(['reason' => 'other', 'note' => 'Guest left']),
        lineVoidPayload(['reason' => 'other', 'note' => null]),
        lineVoidPayload(['reason' => 'wrong_item']),
    ]]);

    expect(Validator::make($payload, TicketData::validationRules())->errors()->keys())->toBe(['voids.1.note']);
});
