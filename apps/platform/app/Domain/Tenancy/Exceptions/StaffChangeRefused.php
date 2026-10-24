<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/** A change to a staff member the person asking may not make. $reason is one of the constants. */
final class StaffChangeRefused extends RuntimeException
{
    /** The target is outside what this person looks after. */
    public const NOT_YOURS = 'not_yours';

    public const ROLE_NOT_ALLOWED = 'role_not_allowed';

    public const BRANCH_NOT_ALLOWED = 'branch_not_allowed';

    /** Everyone but an owner works at one branch at least. */
    public const NEEDS_BRANCH = 'needs_branch';

    /** Nobody changes their own role or switches themselves off. */
    public const SELF = 'self';

    /** The business always keeps one active owner. */
    public const LAST_OWNER = 'last_owner';

    public const INACTIVE = 'inactive';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Staff change refused: {$reason}.");
    }
}
