<?php

use App\Support\Price;

it('formats whole shillings, rounding half up only at presentation', function (int $cents, string $expected) {
    expect(Price::shillings($cents))->toBe($expected);
})->with([
    [349_900, 'KES 3,499'],
    [349_949, 'KES 3,499'],
    [349_950, 'KES 3,500'],
    [0, 'KES 0'],
]);

it('works out annual billing in integer cents', function () {
    expect(Price::annualTotal(349_900, 10))->toBe(3_499_000)
        ->and(Price::annualMonthlyEquivalent(349_900, 10))->toBe(291_583)
        ->and(Price::shillings(Price::annualMonthlyEquivalent(349_900, 10)))->toBe('KES 2,916');
});
