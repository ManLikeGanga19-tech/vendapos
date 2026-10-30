<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Data\PaymentRequestData;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentChannel;

/** @return array<string, mixed> */
function paymentPayload(array $overrides = []): array
{
    return array_merge([
        'uuid' => fake()->uuid(),
        'order_uuid' => fake()->uuid(),
        'provider' => 'mpesa_ke',
        'phone' => '+254712345678',
        'amount_cents' => 185_000,
    ], $overrides);
}

it('creates from a valid payload', function () {
    $payment = PaymentRequestData::from(paymentPayload());

    expect($payment->provider)->toBe(MobileMoneyProvider::MpesaKe)
        ->and($payment->amount()->toWholeShillings())->toBe(1_850);
});

it('accepts a payment before its order exists', function () {
    expect(Validator::make(paymentPayload(['order_uuid' => null]), PaymentRequestData::rules())->passes())->toBeTrue();
});

it('refuses amounts with cents, zero amounts and unknown providers', function (array $overrides, string $field) {
    expect(Validator::make(paymentPayload($overrides), PaymentRequestData::rules())->errors()->keys())->toContain($field);
})->with([
    'cents' => [['amount_cents' => 123_450], 'amount_cents'],
    'zero' => [['amount_cents' => 0], 'amount_cents'],
    'provider' => [['provider' => 'mpesa_tz'], 'provider'],
    'phone' => [['phone' => '12'], 'phone'],
]);

it('carries no tenant, branch or device', function () {
    $properties = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(PaymentRequestData::class))->getProperties());

    expect($properties)->not->toContain('tenant_id')->not->toContain('branch_id')->not->toContain('device_id');
});

it('is a push to a phone unless it asks for a QR code', function () {
    expect(PaymentRequestData::from(paymentPayload())->channel)->toBe(PaymentChannel::Push)
        ->and(Validator::make(paymentPayload(['phone' => null]), PaymentRequestData::rules())->errors()->keys())->toContain('phone');
});

it('takes a QR payment with no phone, and refuses one with a phone', function () {
    $qr = paymentPayload(['channel' => 'qr']);
    unset($qr['phone']);

    expect(Validator::make($qr, PaymentRequestData::rules())->passes())->toBeTrue()
        ->and(PaymentRequestData::from($qr))->channel->toBe(PaymentChannel::Qr)->phone->toBeNull()
        ->and(Validator::make(paymentPayload(['channel' => 'qr']), PaymentRequestData::rules())->errors()->keys())->toContain('phone')
        ->and(Validator::make(paymentPayload(['channel' => 'sms']), PaymentRequestData::rules())->errors()->keys())->toContain('channel');
});
