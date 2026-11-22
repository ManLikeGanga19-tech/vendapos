<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/**
 * Scan-to-pay is for paybill accounts only (Daniel, 2026-10-30): a buy-goods
 * payment carries no account reference, so it could not be matched to its bill.
 */
final class QrNeedsPaybill extends RuntimeException
{
    public const string CODE = 'qr_needs_paybill';
}
