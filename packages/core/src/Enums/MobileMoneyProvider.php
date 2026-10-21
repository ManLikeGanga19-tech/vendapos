<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/**
 * A mobile-money network a terminal can push a payment request to. Kenya
 * first (approved by Daniel, M1); Tanzania's Airtel and Vodacom M-Pesa are
 * added later as their own cases with their own country and currency.
 */
enum MobileMoneyProvider: string
{
    case MpesaKe = 'mpesa_ke';
    case AirtelKe = 'airtel_ke';

    /** ISO 3166-1 alpha-2 */
    public function country(): string
    {
        return match ($this) {
            self::MpesaKe, self::AirtelKe => 'KE',
        };
    }

    /** ISO 4217 */
    public function currency(): string
    {
        return match ($this) {
            self::MpesaKe, self::AirtelKe => 'KES',
        };
    }

    /** International dialling code the customer's phone must carry, without "+". */
    public function dialCode(): string
    {
        return match ($this) {
            self::MpesaKe, self::AirtelKe => '254',
        };
    }

    public function paymentType(): PaymentType
    {
        return match ($this) {
            self::MpesaKe => PaymentType::Mpesa,
            self::AirtelKe => PaymentType::AirtelMoney,
        };
    }
}
