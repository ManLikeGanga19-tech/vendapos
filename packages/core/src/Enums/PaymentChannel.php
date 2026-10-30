<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** How a mobile-money payment reaches the customer. */
enum PaymentChannel: string
{
    // A prompt (M-Pesa Express / STK push) sent to the customer's phone by the cashier.
    case Push = 'push';
    // A QR code printed on the bill that the customer scans to pay the paybill (Dynamic QR).
    case Qr = 'qr';
}
