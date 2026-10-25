<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * A pairing code that can't be used: unknown, expired, already used, or for
 * a suspended business. The till is told only that, never which, so a
 * guesser learns nothing; $reason is for the security log.
 */
final class PairingCodeRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Pairing code rejected.');
    }
}
