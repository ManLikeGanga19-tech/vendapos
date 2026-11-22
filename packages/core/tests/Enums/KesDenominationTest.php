<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\KesDenomination;

it('lists the notes and coins counted at cash-up, largest first', function () {
    expect(KesDenomination::shillings())->toBe([1000, 500, 200, 100, 50, 40, 20, 10, 5, 1]);
});

it('knows each one’s value in cents and whether it is a note', function () {
    expect(KesDenomination::Note1000->value()->cents)->toBe(100_000)
        ->and(KesDenomination::Coin1->value()->cents)->toBe(100)
        ->and(KesDenomination::Note50->isNote())->toBeTrue()
        ->and(KesDenomination::Coin40->isNote())->toBeFalse();
});
