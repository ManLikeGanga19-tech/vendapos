<?php

declare(strict_types=1);

namespace App\Staff;

use RuntimeException;
use Throwable;

/** The PIN was not changed. `reason` is the key of the message under terminal.pin_change.reasons. */
final class PinChangeFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("PIN change failed: {$reason}", previous: $previous);
    }
}
