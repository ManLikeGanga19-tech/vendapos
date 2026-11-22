<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\CashCountData;
use VendaPOS\Core\Enums\KesDenomination;

it('works the total out from the counts', function () {
    $count = CashCountData::from(['counts' => ['1000' => 3, '200' => 2, '50' => 1, '1' => 7]]);

    expect($count->total()->cents)->toBe(345_700)
        ->and($count->countOf(KesDenomination::Note200))->toBe(2)
        ->and($count->countOf(KesDenomination::Note500))->toBe(0);
});

it('counts an empty drawer as nothing', function () {
    expect(CashCountData::from(['counts' => []])->total()->isZero())->toBeTrue();
});

it('accepts only Kenyan notes and coins, in whole non-negative counts', function (array $counts) {
    expect(Validator::make(['counts' => $counts], CashCountData::rules())->passes())->toBeFalse();
})->with([
    'a 2000 note' => [['2000' => 1]],
    'a negative count' => [['100' => -1]],
    'half a coin' => [['5' => 1.5]],
    'a typo of millions' => [['1000' => CashCountData::MAX_PER_DENOMINATION + 1]],
]);

it('accepts a real drawer', function () {
    expect(Validator::make(['counts' => ['1000' => 12, '500' => 4, '40' => 2, '1' => 0]], CashCountData::rules())->passes())->toBeTrue();
});
