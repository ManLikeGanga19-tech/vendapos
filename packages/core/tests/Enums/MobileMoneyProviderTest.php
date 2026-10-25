<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentType;

it('knows each provider’s country, currency, dialling code and payment type', function (MobileMoneyProvider $provider, PaymentType $type) {
    expect($provider->country())->toBe('KE')
        ->and($provider->currency())->toBe('KES')
        ->and($provider->dialCode())->toBe('254')
        ->and($provider->paymentType())->toBe($type);
})->with([
    [MobileMoneyProvider::MpesaKe, PaymentType::Mpesa],
    [MobileMoneyProvider::AirtelKe, PaymentType::AirtelMoney],
]);
