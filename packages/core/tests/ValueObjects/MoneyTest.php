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
