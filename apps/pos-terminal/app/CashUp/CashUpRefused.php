<?php

declare(strict_types=1);

namespace App\CashUp;

use RuntimeException;

/** A drawer action the till will not do; $reason keys terminal.cash_up.refused.*. */
final class CashUpRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Cash-up refused: {$reason}");
    }
}
