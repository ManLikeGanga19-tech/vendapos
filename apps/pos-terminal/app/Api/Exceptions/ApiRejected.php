<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/**
 * The server understood the request and said no (404 or 422). The message is
 * safe to show; `reason` is the server's machine-readable code, when it sent one.
 */
final class ApiRejected extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }
}
