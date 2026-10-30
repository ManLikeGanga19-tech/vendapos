<?php

declare(strict_types=1);

namespace App\Sales;

/**
 * The number printed on a receipt: the till's code and its own sale number,
 * "T2-000123". The server keeps the same number for the owner's audit
 * (App\Domain\Orders\Support\ReceiptNumber there); change both together.
 */
final class ReceiptNumber
{
    public static function format(string $tillCode, int $sequenceNumber): string
    {
        return sprintf('%s-%06d', $tillCode, $sequenceNumber);
    }
}
