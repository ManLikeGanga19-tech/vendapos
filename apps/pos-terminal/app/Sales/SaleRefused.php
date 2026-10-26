<?php

declare(strict_types=1);

namespace App\Sales;

use RuntimeException;

/** A sale that was not recorded; $reason keys terminal.sale.refused, $errors are core's details. */
final class SaleRefused extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly string $reason, public readonly array $errors = [])
    {
        parent::__construct($reason.($errors === [] ? '' : ': '.implode(' ', $errors)));
    }
}
