<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\ShiftData;

/** @return array<string, mixed> */
function shiftPayload(array $overrides = []): array
{
    return array_merge([
        'uuid' => fake()->uuid(),
        'number' => 12,
        'opened_at' => '2026-11-21T07:00:00+03:00',
        'opened_by' => fake()->uuid(),
        'opening_float' => ['counts' => ['1000' => 2, '100' => 10]],
        'movements' => [],
    ], $overrides);
}

/** @return array<string, mixed> */
function closedShift(array $overrides = []): array
{
    return shiftPayload(array_merge([
        'closed_at' => '2026-11-21T15:00:00+03:00',
        'closed_by' => fake()->uuid(),
        'closing_count' => ['counts' => ['1000' => 5]],
        'expected_cents' => 500_000,
    ], $overrides));
}

/** @return list<string> */
function shiftErrors(array $payload): array
{
    return Validator::make($payload, ShiftData::rules())->errors()->keys();
}

it('reads an open shift with its float', function () {
    $shift = ShiftData::from(shiftPayload());

    expect($shift->isClosed())->toBeFalse()
        ->and($shift->float()->cents)->toBe(300_000)
        ->and($shift->movementList())->toBe([])
        ->and(shiftErrors(shiftPayload()))->toBe([]);
});

it('reads a closed shift with its count and movements', function () {
    $payload = closedShift(['movements' => [[
        'uuid' => fake()->uuid(), 'kind' => 'drop', 'amount_cents' => 200_000,
        'moved_at' => '2026-11-21T12:00:00+03:00', 'staff_id' => fake()->uuid(), 'approved_by' => fake()->uuid(),
    ]]]);
    $shift = ShiftData::from($payload);

    expect($shift->isClosed())->toBeTrue()
        ->and($shift->closing_count?->total()->cents)->toBe(500_000)
        ->and($shift->movementList())->toHaveCount(1)
        ->and(shiftErrors($payload))->toBe([]);
});

it('closes all at once: who, the count and the till’s expected cash', function (string $missing) {
    expect(shiftErrors(closedShift([$missing => null])))->toContain($missing);
})->with(['closed_by', 'closing_count', 'expected_cents']);

it('has no count, closer or approval while open', function (string $field, mixed $value) {
    expect(shiftErrors(shiftPayload([$field => $value])))->toContain($field);
})->with([
    ['closed_by', '0199b1f2-0000-7000-8000-000000000001'],
    ['closing_count', ['counts' => ['1000' => 1]]],
    ['expected_cents', 100],
    ['approved_by', '0199b1f2-0000-7000-8000-000000000002'],
]);

it('needs a note with a manager’s approval, and closes after it opened', function () {
    expect(shiftErrors(closedShift(['approved_by' => fake()->uuid()])))->toContain('note')
        ->and(shiftErrors(closedShift(['approved_by' => fake()->uuid(), 'note' => 'KES 300 short, counted twice'])))->toBe([])
        ->and(shiftErrors(closedShift(['closed_at' => '2026-11-21T06:59:00+03:00'])))->toContain('closed_at');
});

it('checks the counts and movements inside the shift', function () {
    expect(shiftErrors(shiftPayload(['opening_float' => ['counts' => ['3' => 1]]])))->toContain('opening_float.counts')
        ->and(shiftErrors(closedShift(['closing_count' => ['counts' => ['100' => -2]]])))->toContain('closing_count.counts.100')
        ->and(shiftErrors(shiftPayload(['movements' => [['kind' => 'drop']]])))->toContain('movements.0.uuid')
        ->and(shiftErrors(shiftPayload(['movements' => array_fill(0, ShiftData::MAX_MOVEMENTS + 1, [])])))->toContain('movements');
});

it('allows a negative expected figure, so the server can flag it', function () {
    expect(shiftErrors(closedShift(['expected_cents' => -5_000])))->toBe([]);
});
