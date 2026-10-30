<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

use Illuminate\Validation\Validator;
use VendaPOS\Core\Data\PaymentRequestData;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Rules\PhoneNumber;

final class RequestPaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        return PaymentRequestData::rules();
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator) {
                $provider = MobileMoneyProvider::tryFrom((string) $this->input('provider'));
                $phone = (string) $this->input('phone');

                // Providers without a gateway yet (Airtel is deferred) are refused up front, not failed later.
                if ($provider !== null && ! config('vendapos.payments.fake') && config("vendapos.payments.gateways.{$provider->value}") === null) {
                    $validator->errors()->add('provider', "{$provider->value} is not available yet.");
                }

                // An M-Pesa Kenya push can only go to a Kenyan number, and so on. A QR payment has no phone.
                if ($provider !== null && $this->input('phone') !== null && ! $validator->errors()->has('phone') && ! str_starts_with($phone, '+'.$provider->dialCode())) {
                    $validator->errors()->add('phone', "The phone must be a +{$provider->dialCode()} number for {$provider->value}.");
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge(['phone' => PhoneNumber::normalize($phone) ?? $phone]);
        }
    }
}
