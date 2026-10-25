<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/** Someone changing their own PIN on a till was refused. $reason is one of the constants. */
final class PinChangeRefused extends RuntimeException
{
    /** Not on this till's staff list: no such person, inactive, no PIN yet, or another branch. */
    public const NOT_FOUND = 'not_found';

    public const WRONG_PIN = 'wrong_pin';

    public const TOO_MANY_TRIES = 'too_many_tries';

    public const WRONG_LENGTH = 'wrong_length';

    /** One digit repeated or a straight run, like 0000 or 1234. */
    public const TOO_EASY = 'too_easy';

    public const SAME_PIN = 'same_pin';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("PIN change refused: {$reason}.");
    }
}
