<?php

declare(strict_types=1);

use VendaPOS\Core\ValueObjects\Money;

it('creates from cents', function () {
    $money = Money::fromCents(15000);
    expect($money->cents)->toBe(15000);
});

it('adds two amounts', function () {
    $a = Money::fromCents(10000);
    $b = Money::fromCents(5000);
    expect($a->add($b)->cents)->toBe(15000);
});

it('subtracts two amounts', function () {
    $a = Money::fromCents(10000);
    $b = Money::fromCents(3000);
    expect($a->subtract($b)->cents)->toBe(7000);
});

it('multiplies by quantity', function () {
    $money = Money::fromCents(2500);
    expect($money->multiply(3)->cents)->toBe(7500);
});

it('allocates evenly', function () {
    $money = Money::fromCents(10000);
    $parts = $money->allocate(4);

    expect($parts)->toHaveCount(4);
    expect($parts[0]->cents)->toBe(2500);
    expect($parts[3]->cents)->toBe(2500);
});

it('distributes remainder one cent at a time', function () {
    $money = Money::fromCents(10001);
    $parts = $money->allocate(3);

    expect($parts)->toHaveCount(3);
    // 10001 / 3 = 3333 remainder 2, so first two get +1
    expect($parts[0]->cents)->toBe(3334);
    expect($parts[1]->cents)->toBe(3334);
    expect($parts[2]->cents)->toBe(3333);
    // Total must equal original
    expect($parts[0]->cents + $parts[1]->cents + $parts[2]->cents)->toBe(10001);
});

it('throws on zero parts', function () {
    Money::fromCents(10000)->allocate(0);
})->throws(InvalidArgumentException::class);

it('formats as KES', function () {
    expect(Money::fromCents(123456)->format())->toBe('KES 1,234.56');
});

it('serializes to integer cents', function () {
    $money = Money::fromCents(5000);
    expect(json_encode($money))->toBe('5000');
});

it('checks equality', function () {
    $a = Money::fromCents(1000);
    $b = Money::fromCents(1000);
    $c = Money::fromCents(2000);

    expect($a->equals($b))->toBeTrue();
    expect($a->equals($c))->toBeFalse();
});

it('checks zero', function () {
    expect(Money::zero()->isZero())->toBeTrue();
    expect(Money::fromCents(1)->isZero())->toBeFalse();
});

it('is immutable', function () {
    $original = Money::fromCents(1000);
    $result = $original->add(Money::fromCents(500));

    expect($original->cents)->toBe(1000);
    expect($result->cents)->toBe(1500);
});

it('converts to whole shillings for mobile money and refuses amounts with cents', function () {
    expect(Money::fromCents(185_000)->toWholeShillings())->toBe(1_850)
        ->and(Money::fromCents(123_450)->isWholeShillings())->toBeFalse()
        ->and(fn () => Money::fromCents(123_450)->toWholeShillings())->toThrow(InvalidArgumentException::class);
});

it('reads shillings as typed, without floats', function (string $typed, int $cents) {
    expect(Money::fromShillings($typed)->cents)->toBe($cents);
})->with([
    'whole' => ['1250', 125_000],
    'one decimal' => ['1250.5', 125_050],
    'two decimals' => ['1250.05', 125_005],
    'thousands separators' => ['1,250.50', 125_050],
    'padded' => [' 0.10 ', 10],
    'float trap' => ['0.29', 29],
    'zero' => ['0', 0],
]);

it('refuses amounts it would have to round or guess', function (string $typed) {
    Money::fromShillings($typed);
})->throws(InvalidArgumentException::class)->with([
    'three decimals' => ['12.345'],
    'negative' => ['-5'],
    'plus sign' => ['+5'],
    'exponent' => ['1e3'],
    'empty' => [''],
    'dot only' => ['.'],
    'trailing dot' => ['12.'],
    'letters' => ['KES 12'],
    'too long' => ['12345678901234'],
]);

it('writes shillings for a form field and reads them back', function (int $cents, string $shillings) {
    expect(Money::fromCents($cents)->toShillings())->toBe($shillings)
        ->and(Money::fromShillings($shillings)->cents)->toBe($cents);
})->with([
    [125_050, '1250.50'],
    [5, '0.05'],
    [0, '0.00'],
]);
