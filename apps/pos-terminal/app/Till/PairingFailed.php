<?php

declare(strict_types=1);

namespace App\Till;

use RuntimeException;
use Throwable;

/** Pairing did not happen. `reason` is the key of the message under terminal.pair. */
final class PairingFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("Pairing failed: {$reason}", previous: $previous);
    }
}
