<?php

declare(strict_types=1);

use VendaPOS\Core\CashUp\ShiftCash;
use VendaPOS\Core\Data\CashMovementData;
use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\CashMovementKind;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\ValueObjects\Money;

function cashOut(CashMovementKind $kind, int $cents): CashMovementData
{
    return new CashMovementData(fake()->uuid(), $kind, $cents, '2026-11-21T12:00:00+03:00', fake()->uuid());
}

beforeEach(function () {
    $this->tenders = [
        // KES 1,000 handed over for a KES 650 sale: 350 change, so 650 stays in the drawer.
        new TenderData(TenderMethod::Cash, 65_000, 100_000, 35_000),
        new TenderData(TenderMethod::MpesaPush, 120_000, payment_uuid: fake()->uuid()),
        new TenderData(TenderMethod::MpesaCode, 30_000, mpesa_reference: 'SKL1ABCDEF'),
        // Split sale: the cash part only.
        new TenderData(TenderMethod::Cash, 20_000, 20_000, 0),
    ];
    $this->movements = [
        cashOut(CashMovementKind::PayOut, 15_000),
        cashOut(CashMovementKind::Drop, 50_000),
        cashOut(CashMovementKind::VoidRefund, 20_000),
    ];
    $this->cash = ShiftCash::for(Money::fromCents(500_000), $this->tenders, $this->movements);
});

it('expects float plus cash taken, less everything that left the drawer', function () {
    expect($this->cash->cashTaken->cents)->toBe(85_000)
        ->and($this->cash->payOuts->cents)->toBe(15_000)
        ->and($this->cash->drops->cents)->toBe(50_000)
        ->and($this->cash->voidRefunds->cents)->toBe(20_000)
        // 5,000 + 850 − 150 − 500 − 200
        ->and($this->cash->expected()->cents)->toBe(500_000);
});

it('leaves M-Pesa out of the drawer', function () {
    $mpesaOnly = ShiftCash::for(Money::fromCents(100_000), array_slice($this->tenders, 1, 2), []);

    expect($mpesaOnly->expected()->cents)->toBe(100_000);
});

it('says short below zero and over above zero', function () {
    expect($this->cash->variance(Money::fromCents(470_000))->cents)->toBe(-30_000)
        ->and($this->cash->variance(Money::fromCents(500_500))->cents)->toBe(500)
        ->and($this->cash->variance(Money::fromCents(500_000))->isZero())->toBeTrue();
});

it('needs a manager only when the count is off by more than the business allows', function () {
    $allowed = Money::fromCents(10_000);

    expect($this->cash->needsApproval(Money::fromCents(490_000), $allowed))->toBeFalse()
        ->and($this->cash->needsApproval(Money::fromCents(510_000), $allowed))->toBeFalse()
        ->and($this->cash->needsApproval(Money::fromCents(489_999), $allowed))->toBeTrue()
        ->and($this->cash->needsApproval(Money::fromCents(510_001), $allowed))->toBeTrue();
});

it('goes negative when more left the drawer than came in, rather than hiding it', function () {
    $overspent = ShiftCash::for(Money::zero(), [], [cashOut(CashMovementKind::PayOut, 5_000)]);

    expect($overspent->expected()->cents)->toBe(-5_000);
});
