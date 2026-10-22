<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** What VendaPOS sends to KRA eTIMS for an order. */
enum EtimsDocumentType: string
{
    case Sale = 'sale';
    // Reverses a submitted sale when the order is voided.
    case CreditNote = 'credit_note';
}
