<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use VendaPOS\Core\Rules\PhoneNumber;

it('normalises phone numbers to E.164', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'Safaricom local' => ['0712 345 678', '+254712345678'],
    'new 01 prefix' => ['0110-123-456', '+254110123456'],
    'without plus' => ['254712345678', '+254712345678'],
    'international' => ['+254 712 345 678', '+254712345678'],
    'Uganda' => ['+256 772 123456', '+256772123456'],
    'double-zero prefix' => ['00255754123456', '+255754123456'],
    'too short' => ['0712 345', null],
    'letters' => ['07I2345678', null],
    'landline without code' => ['020 1234567', null],
    'empty' => ['', null],
]);

it('validates with a custom message', function () {
    $validator = Validator::make(['phone' => '12'], ['phone' => [new PhoneNumber('Namba ya simu si sahihi.')]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('phone'))->toBe('Namba ya simu si sahihi.');
});

it('rejects non-string values', function () {
    expect(Validator::make(['phone' => 712345678], ['phone' => [new PhoneNumber]])->fails())->toBeTrue();
});
