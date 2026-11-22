<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** How one part of a sale was paid. Card and Airtel Money come later. */
enum TenderMethod: string
{
    case Cash = 'cash';
    // A push to the customer's phone started by the till (a Payment on the server).
    case MpesaPush = 'mpesa_push';
    // The customer paid the Paybill or Till themselves; the cashier typed the M-Pesa code.
    case MpesaCode = 'mpesa_code';

    public function paymentType(): PaymentType
    {
        return match ($this) {
            self::Cash => PaymentType::Cash,
            self::MpesaPush, self::MpesaCode => PaymentType::Mpesa,
        };
    }

    /** Mobile money moves whole shillings only, so these tenders take no cents. */
    public function wholeShillingsOnly(): bool
    {
        return $this !== self::Cash;
    }
}
