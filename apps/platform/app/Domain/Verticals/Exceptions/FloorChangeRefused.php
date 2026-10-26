<?php

declare(strict_types=1);

namespace App\Domain\Verticals\Exceptions;

use RuntimeException;

/** A floor plan change that was not made; $reason keys owner.tables.refused. */
final class FloorChangeRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
