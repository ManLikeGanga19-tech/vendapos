<?php

declare(strict_types=1);

use VendaPOS\Core\Data\TenderData;
use VendaPOS\Core\Enums\OrderStatus;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\Enums\TenderMethod;
use VendaPOS\Core\Payments\Tenders;

function cashTender(int $amount, ?int $tendered = null, ?int $change = null): TenderData
{
    $tendered ??= $amount;

    return new TenderData(TenderMethod::Cash, $amount, tendered_cents: $tendered, change_cents: $change ?? $tendered - $amount);
}

function pushTender(int $amount, ?string $paymentUuid = null): TenderData
{
    return new TenderData(TenderMethod::MpesaPush, $amount, payment_uuid: $paymentUuid ?? fake()->uuid());
}

function codeTender(int $amount, string $reference = 'SJ12ABC3DE'): TenderData
{
    return new TenderData(TenderMethod::MpesaCode, $amount, mpesa_reference: $reference);
}

it('accepts a sale paid exactly, with change from cash', function () {
    expect(Tenders::problems([cashTender(185_000, tendered: 200_000)], OrderStatus::Completed, 185_000))->toBe([])
        ->and(Tenders::problems([pushTender(100_000), codeTender(50_000), cashTender(35_050)], OrderStatus::Completed, 185_050))->toBe([]);
});

it('refuses a sale paid short or paid over', function (int $total) {
    expect(Tenders::problems([cashTender(100_000)], OrderStatus::Completed, $total))->toHaveKey('tenders');
})->with(['short' => 100_100, 'over' => 99_900]);

it('needs a tender on a completed or voided sale, and none on a cancelled one', function () {
    expect(Tenders::problems([], OrderStatus::Completed, 10_000))->toHaveKey('tenders')
        ->and(Tenders::problems([], OrderStatus::Voided, 10_000))->toHaveKey('tenders')
        ->and(Tenders::problems([], OrderStatus::Cancelled, 10_000))->toBe([])
        ->and(Tenders::problems([cashTender(10_000)], OrderStatus::Cancelled, 10_000))->toHaveKey('tenders');
});

it('takes M-Pesa in whole shillings only, so the cents are paid in cash', function () {
    expect(Tenders::problems([pushTender(9_950)], OrderStatus::Completed, 9_950))->toHaveKey('tenders.0.amount_cents')
        ->and(Tenders::problems([codeTender(9_950)], OrderStatus::Completed, 9_950))->toHaveKey('tenders.0.amount_cents')
        ->and(Tenders::problems([pushTender(9_900), cashTender(50)], OrderStatus::Completed, 9_950))->toBe([])
        ->and(Tenders::problems([cashTender(9_950)], OrderStatus::Completed, 9_950))->toBe([]);
});

it('checks the cash handed over and the change', function () {
    expect(Tenders::problems([cashTender(10_000, tendered: 5_000, change: 0)], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.tendered_cents')
        ->and(Tenders::problems([cashTender(10_000, tendered: 20_000, change: 5_000)], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.change_cents')
        ->and(Tenders::problems([new TenderData(TenderMethod::Cash, 10_000)], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.tendered_cents');
});

it('keeps each field to its own method', function () {
    $pushWithChange = new TenderData(TenderMethod::MpesaPush, 10_000, tendered_cents: 10_000, change_cents: 0, payment_uuid: fake()->uuid());
    $pushWithoutPayment = new TenderData(TenderMethod::MpesaPush, 10_000);
    $codeWithPayment = new TenderData(TenderMethod::MpesaCode, 10_000, payment_uuid: fake()->uuid(), mpesa_reference: 'SJ12ABC3DE');
    $cashWithCode = new TenderData(TenderMethod::Cash, 10_000, tendered_cents: 10_000, change_cents: 0, mpesa_reference: 'SJ12ABC3DE');

    expect(Tenders::problems([$pushWithChange], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.tendered_cents')
        ->and(Tenders::problems([$pushWithoutPayment], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.payment_uuid')
        ->and(Tenders::problems([$codeWithPayment], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.payment_uuid')
        ->and(Tenders::problems([$cashWithCode], OrderStatus::Completed, 10_000))->toHaveKey('tenders.0.mpesa_reference');
});

it('takes cash once, and each payment or M-Pesa code once', function () {
    $payment = fake()->uuid();

    expect(Tenders::problems([cashTender(5_000), cashTender(5_000)], OrderStatus::Completed, 10_000))->toHaveKey('tenders')
        ->and(Tenders::problems([pushTender(5_000, $payment), pushTender(5_000, $payment)], OrderStatus::Completed, 10_000))->toHaveKey('tenders.1.payment_uuid')
        ->and(Tenders::problems([codeTender(5_000), codeTender(5_000)], OrderStatus::Completed, 10_000))->toHaveKey('tenders.1.mpesa_reference');
});

it('caps the number of tenders', function () {
    $tenders = array_map(fn () => codeTender(1_000, strtoupper(fake()->bothify('??########'))), range(1, Tenders::MAX_TENDERS + 1));

    expect(Tenders::problems($tenders, OrderStatus::Completed, 11_000))->toHaveKey('tenders');
});

it('derives one payment type for the order', function () {
    expect(Tenders::paymentType([cashTender(100)]))->toBe(PaymentType::Cash)
        ->and(Tenders::paymentType([pushTender(100)]))->toBe(PaymentType::Mpesa)
        ->and(Tenders::paymentType([pushTender(100), codeTender(100)]))->toBe(PaymentType::Mpesa)
        ->and(Tenders::paymentType([pushTender(100), cashTender(50)]))->toBe(PaymentType::Split)
        ->and(Tenders::paymentType([]))->toBeNull();
});
