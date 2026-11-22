<?php

declare(strict_types=1);

namespace App\Sales;

use RuntimeException;

/** A void that did not happen; $reason keys terminal.sales.refused. */
final class VoidRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
