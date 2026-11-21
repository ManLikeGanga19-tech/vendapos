<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\OrderData;
use VendaPOS\Core\Data\VoidData;
use VendaPOS\Core\Enums\VoidReason;

/** @return array<string, mixed> */
function voidPayload(array $overrides = []): array
{
    return array_merge([
        'voided_at' => '2026-10-21T18:45:00+03:00',
        'voided_by' => fake()->uuid(),
        'approved_by' => fake()->uuid(),
        'reason' => 'wrong_item',
        'note' => null,
    ], $overrides);
}

it('creates from a valid payload', function () {
    $void = VoidData::from(voidPayload(['reason' => 'duplicate']));

    expect($void->reason)->toBe(VoidReason::Duplicate)
        ->and($void->note)->toBeNull();
});

it('lets one manager void and approve on their own', function () {
    $manager = fake()->uuid();

    expect(Validator::make(voidPayload(['voided_by' => $manager, 'approved_by' => $manager]), VoidData::rules())->passes())->toBeTrue();
});

it('needs who voided, who approved and a reason from the list', function (string $field, mixed $value) {
    $errors = Validator::make(voidPayload([$field => $value]), VoidData::rules())->errors();

    expect($errors->keys())->toContain($field);
})->with([
    ['voided_by', null],
    ['voided_by', 'manager-1'],
    ['approved_by', null],
    ['approved_by', 'manager-1'],
    ['reason', null],
    ['reason', 'felt_like_it'],
    ['voided_at', 'yesterday-ish'],
]);

it('needs a note only when the reason is "other", and keeps it short', function () {
    expect(Validator::make(voidPayload(['reason' => 'other']), VoidData::rules())->errors()->keys())->toContain('note')
        ->and(Validator::make(voidPayload(['reason' => 'other', 'note' => 'Customer walked out']), VoidData::rules())->passes())->toBeTrue()
        ->and(Validator::make(voidPayload(['note' => str_repeat('x', VoidData::NOTE_MAX + 1)]), VoidData::rules())->errors()->keys())->toContain('note')
        ->and(VoidReason::Other->needsNote())->toBeTrue()
        ->and(VoidReason::WrongItem->needsNote())->toBeFalse();
});

it('rides on a voided order, and only on a voided order', function () {
    $voided = orderPayload(['status' => 'voided', 'void' => voidPayload()]);

    expect(Validator::make($voided, OrderData::validationRules())->passes())->toBeTrue()
        ->and(OrderData::from($voided)->void?->reason)->toBe(VoidReason::WrongItem)
        ->and(Validator::make(orderPayload(['status' => 'voided']), OrderData::validationRules())->errors()->keys())->toContain('void')
        ->and(Validator::make(orderPayload(['void' => voidPayload()]), OrderData::validationRules())->errors()->keys())->toContain('void')
        ->and(OrderData::from(orderPayload())->void)->toBeNull();
});

it('checks the void inside a voided order', function () {
    $errors = Validator::make(orderPayload(['status' => 'voided', 'void' => voidPayload(['approved_by' => null, 'reason' => 'other'])]), OrderData::validationRules())->errors();

    expect($errors->keys())->toContain('void.approved_by')
        ->toContain('void.note');
});
