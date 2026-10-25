<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;
use Throwable;

/** No answer we can use right now (offline, timeout, server error, rate limit): try again later. */
final class ApiUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
