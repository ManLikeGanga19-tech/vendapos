<?php

declare(strict_types=1);

namespace App\Domain\Orders\Support;

/**
 * The number printed on a receipt: the till's code and its own sale number,
 * "T2-000123". Unique in a business because each till numbers its own sales.
 * The till prints the same format; change both together.
 */
final class ReceiptNumber
{
    public static function format(int $tillNumber, int $sequenceNumber): string
    {
        return sprintf('T%d-%06d', $tillNumber, $sequenceNumber);
    }
}
