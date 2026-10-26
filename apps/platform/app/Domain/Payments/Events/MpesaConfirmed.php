<?php

declare(strict_types=1);

namespace App\Domain\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Safaricom confirmed money paid straight into an account (C2B), and it is stored. */
final class MpesaConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $confirmationId,
    ) {}
}
